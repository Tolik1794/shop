<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Store;
use App\Entity\Customer;
use App\Entity\User\User;
use App\Form\Admin\FilterType\OrderFilterType;
use App\Form\Admin\Type\OrderType;
use App\Manager\OrderManager;
use App\Manager\OrderCommentManager;
use App\Repository\CustomerRepository;
use App\Repository\OrderHistoryRepository;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/order', name: 'app_admin_order_'), IsGranted('ROLE_STORE_ADMIN')]
class OrderController extends AbstractAdvancedController
{
	private const int DEFAULT_PAGE_LIMIT = 20;

	public function __construct(
		private readonly OrderManager $orderManager,
		private readonly OrderCommentManager $orderCommentManager,
		private readonly CustomerRepository $customerRepository,
		private readonly OrderHistoryRepository $orderHistoryRepository,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		FilterFormHandler $filterFormHandler,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$queryBuilder = $this->orderManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(OrderFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['orders.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$order = $this->orderManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
			]);
		} else {
			$order = $pagination->current();
		}

		return $this->render('admin/order/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $order,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$order = $this->orderManager->createDraft($store);
		$form = $this->createOrderForm($order, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted()) {
			$this->resolveOrderCustomer($form, $order, $store);
		}

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$this->orderManager->saveOrder($order);
				$this->saveDraftComments($form, $order);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->render('admin/order/form.html.twig', [
					'entity' => $order,
					'form' => $form,
					'order_index_page' => null,
				]);
			}

			return $this->stayOrRedirect(
				route: 'app_admin_order_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_order_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $order->getId()],
			);
		}

		return $this->render('admin/order/form.html.twig', [
			'entity' => $order,
			'form' => $form,
			'order_index_page' => null,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$this->denyNotDraft($order);

		$originalEntries = [];
		foreach ($order->getOrderEntries() as $orderEntry) {
			$originalEntries[$orderEntry->getId()] = $orderEntry;
		}

		$form = $this->createOrderForm($order, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted()) {
			$this->resolveOrderCustomer($form, $order, $store);
		}

		if ($form->isSubmitted() && $form->isValid()) {
			$removedEntries = array_filter($originalEntries, static fn(OrderEntry $orderEntry) => !$order->getOrderEntries()->contains($orderEntry));

			try {
				$this->orderManager->saveOrder($order, $removedEntries);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->render('admin/order/form.html.twig', [
					'entity' => $order,
					'form' => $form,
					'order_index_page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
					...$this->getOrderDiscussionViewData($order),
				]);
			}

			return $this->stayOrRedirect('app_admin_order_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/order/form.html.twig', [
			'entity' => $order,
			'form' => $form,
			'order_index_page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
			...$this->getOrderDiscussionViewData($order),
		]);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		return $this->render('admin/order/show.html.twig', [
			'entity' => $order,
			...$this->getOrderDiscussionViewData($order),
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/comments', name: 'comments', methods: ['GET'])]
	public function comments(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		return $this->render('admin/order/comments.html.twig', [
			'entity' => $order,
			...$this->getOrderDiscussionViewData($order),
		]);
	}

	#[Route('/{id}/history', name: 'history', methods: ['GET'])]
	public function history(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		return $this->render('admin/order/history.html.twig', [
			'entity' => $order,
			...$this->getOrderDiscussionViewData($order),
		]);
	}

	#[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
	public function confirm(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if ($this->isCsrfTokenValid('confirm_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->orderManager->confirm($order);
			} catch (RuntimeException) {
			}
		}

		return $this->redirectToRoute('app_admin_order_index', [
			'store_id' => $store->getId(),
			'id' => $order->getId(),
			'page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
		]);
	}

	#[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
	public function cancel(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if ($this->isCsrfTokenValid('cancel_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->orderManager->cancel($order);
		}

		return $this->redirectToRoute('app_admin_order_index', [
			'store_id' => $store->getId(),
			'id' => $order->getId(),
			'page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
		]);
	}

	#[Route('/{id}/return-to-draft', name: 'return_to_draft', methods: ['POST'])]
	public function returnToDraft(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if ($this->isCsrfTokenValid('return_to_draft_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->orderManager->returnToDraft($order);
			} catch (RuntimeException) {
			}
		}

		return $this->redirectToRoute('app_admin_order_index', [
			'store_id' => $store->getId(),
			'id' => $order->getId(),
			'page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
		]);
	}

	#[Route('/{id}/comment', name: 'comment_add', methods: ['POST'])]
	public function addComment(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$user = $this->getUser();
		$isSaved = false;

		if ($user instanceof User && $this->isCsrfTokenValid('add_order_comment_' . $order->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->orderCommentManager->create($order, $user, (string) $request->request->get('body'));
				$isSaved = true;
			} catch (RuntimeException) {
			}
		}

		if ($request->isXmlHttpRequest()) {
			return $this->renderDiscussionCard($order, $isSaved ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->redirectToOrderIndex($store, $order);
	}

	#[Route('/{id}/comment/{commentId}/edit', name: 'comment_edit', methods: ['POST'])]
	public function editComment(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
		int $commentId,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$comment = $this->orderCommentManager->find($commentId);
		$user = $this->getUser();
		$isSaved = false;

		if ($comment instanceof OrderComment && $comment->getOrder() === $order && $user instanceof User
			&& $this->isCsrfTokenValid('edit_order_comment_' . $comment->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->orderCommentManager->edit($comment, $user, (string) $request->request->get('body'));
				$isSaved = true;
			} catch (RuntimeException) {
			}
		}

		if ($request->isXmlHttpRequest()) {
			return $this->renderDiscussionCard($order, $isSaved ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->redirectToOrderIndex($store, $order);
	}

	#[Route('/{id}/comment/{commentId}/delete', name: 'comment_delete', methods: ['POST'])]
	public function deleteComment(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
		int $commentId,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$comment = $this->orderCommentManager->find($commentId);
		$user = $this->getUser();
		$isDeleted = false;

		if ($comment instanceof OrderComment && $comment->getOrder() === $order && $user instanceof User
			&& $this->isCsrfTokenValid('delete_order_comment_' . $comment->getId(), (string) $request->request->get('_token'))) {
			$this->orderCommentManager->softDelete($comment, $user);
			$isDeleted = true;
		}

		if ($request->isXmlHttpRequest()) {
			return $this->renderDiscussionCard($order, $isDeleted ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->redirectToOrderIndex($store, $order);
	}

	private function createOrderForm(Order $order, Store $store): FormInterface
	{
		return $this->createForm(OrderType::class, $order, [
			'method' => 'POST',
			'store' => $store,
			'attr' => [
				'data-select-two-target' => 'form',
			],
		]);
	}

	private function resolveOrderCustomer(FormInterface $form, Order $order, Store $store): void
	{
		$phone = $this->getSubmittedString($form, 'customerPhone');
		$name = $this->getSubmittedString($form, 'customerName');
		$lastName = $this->getSubmittedString($form, 'customerLastName');

		if ($phone === '' || $name === '' || $lastName === '') {
			return;
		}

		$customer = $order->getCustomer();

		if ($customer instanceof Customer && $customer->getStore()?->getId() === $store->getId()) {
			return;
		}

		$customer = $this->customerRepository->findOneAvailableByStoreAndPhone($store, $phone);

		if (!$customer instanceof Customer) {
			$customer = (new Customer())
				->setStore($store)
				->setPhone($phone)
				->setName($name)
				->setLastName($lastName);

			$this->orderManager->getEntityManager()->persist($customer);
		}

		$order->setCustomer($customer);
	}

	private function getSubmittedString(FormInterface $form, string $name): string
	{
		if (!$form->has($name)) {
			return '';
		}

		return trim((string) $form->get($name)->getData());
	}

	private function saveDraftComments(FormInterface $form, Order $order): void
	{
		$user = $this->getUser();

		if (!$user instanceof User || !$form->has('draftComments')) {
			return;
		}

		foreach ((array) $form->get('draftComments')->getData() as $body) {
			$this->orderCommentManager->create($order, $user, (string) $body);
		}
	}

	private function denyOrderOutsideStore(Order $order, Store $store): void
	{
		if ($order->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}

	private function denyNotDraft(Order $order): void
	{
		if ($order->getStatus() !== OrderStatus::DRAFT) {
			throw $this->createAccessDeniedException('Only draft order can be changed.');
		}
	}

	private function redirectToOrderIndex(Store $store, Order $order): Response
	{
		return $this->redirectToRoute('app_admin_order_index', [
			'store_id' => $store->getId(),
			'id' => $order->getId(),
			'page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
		]);
	}

	/**
	 * @return array{comments: array, history_entries: array}
	 */
	private function getOrderDiscussionViewData(Order $order): array
	{
		return [
			'comments' => $this->orderCommentManager->getRepository()->findVisibleByOrder($order),
			'history_entries' => $this->orderHistoryRepository->findTimelineByOrder($order),
		];
	}

	private function renderDiscussionCard(Order $order, int $status = Response::HTTP_OK): Response
	{
		$response = $this->render('admin/order/_discussion_card.html.twig', [
			'entity' => $order,
			...$this->getOrderDiscussionViewData($order),
		]);
		$response->setStatusCode($status);

		return $response;
	}
}

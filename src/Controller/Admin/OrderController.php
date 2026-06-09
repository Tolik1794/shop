<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\OrderEntry;
use App\Entity\OrderEntryFulfillmentSource;
use App\Entity\OrderStatus;
use App\Entity\Store;
use App\Entity\Customer;
use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use App\Exception\ConcurrencyConflictException;
use App\Exception\ProductionDemandException;
use App\Form\Admin\FilterType\OrderFilterType;
use App\Form\Admin\Type\OrderType;
use App\Manager\OrderManager;
use App\Manager\OrderCommentManager;
use App\Repository\CustomerRepository;
use App\Repository\OrderCommentReadStateRepository;
use App\Repository\WarehouseRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use App\Service\Customer\CustomerHistoryProvider;
use App\Service\Discount\OrderEntryDiscountService;
use App\Service\FilterFormHandler;
use App\Service\History\DocumentTimelineBuilder;
use App\Service\Inventory\CustomerReturnUseCase;
use App\Service\Inventory\OrderShipmentUseCase;
use App\Service\Order\CommentAudienceResolver;
use App\Service\Order\CommentTemplateProvider;
use App\Service\Payment\OrderPaymentReviewService;
use App\Service\Payment\PaymentDocumentRenderer;
use App\Service\Production\ProductionDemandService;
use App\Workflow\TransitionContext;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/store/{store_id}/order', name: 'app_admin_order_'), IsGranted('order.view')]
class OrderController extends AbstractAdvancedController
{
	use QuickActionResponseTrait;
	use ConcurrencyFormTrait;

	private const int DEFAULT_PAGE_LIMIT = 20;

	public function __construct(
		private readonly OrderManager $orderManager,
		private readonly OrderCommentManager $orderCommentManager,
		private readonly CustomerRepository $customerRepository,
		private readonly DocumentTimelineBuilder $documentTimelineBuilder,
		private readonly OrderShipmentUseCase $orderShipmentUseCase,
		private readonly CustomerReturnUseCase $customerReturnUseCase,
		private readonly ConcurrencyGuard $concurrencyGuard,
		private readonly CommentTemplateProvider $commentTemplateProvider,
		private readonly CommentAudienceResolver $commentAudienceResolver,
		private readonly OrderCommentReadStateRepository $orderCommentReadStateRepository,
		private readonly CustomerHistoryProvider $customerHistoryProvider,
		private readonly OrderPaymentReviewService $orderPaymentReviewService,
		private readonly PaymentDocumentRenderer $paymentDocumentRenderer,
		private readonly ProductionDemandService $productionDemandService,
		private readonly WarehouseRepository $warehouseRepository,
		private readonly TranslatorInterface $translator,
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

		$user = $this->getUser();

		// The shown order is rendered (and read) on this page, so mark it read before counting.
		if ($order instanceof Order && $user instanceof User) {
			$this->orderCommentManager->markOrderRead($order, $user);
		}

		return $this->render('admin/order/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $order,
			'filter_form' => $filterForm->createView(),
			'unread_comment_counts' => $this->unreadCommentCounts($pagination->getItems(), $user),
		]);
	}

	/**
	 * @param iterable<Order> $orders
	 *
	 * @return array<int, int> map of orderId => unread comment count
	 */
	private function unreadCommentCounts(iterable $orders, ?UserInterface $user): array
	{
		if (!$user instanceof User) {
			return [];
		}

		$orderIds = [];

		foreach ($orders as $order) {
			if ($order instanceof Order && $order->getId() !== null) {
				$orderIds[] = $order->getId();
			}
		}

		return $this->orderCommentReadStateRepository->countUnreadByOrders(
			$orderIds,
			$user,
			$this->commentAudienceResolver->relevantTypesFor($user),
		);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('order.create')]
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
			} catch (ConcurrencyConflictException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->render('admin/order/form.html.twig', [
					'entity' => $order,
					'form' => $form,
					'order_index_page' => null,
					...$this->getDraftDiscussionViewData(),
				], new Response(status: Response::HTTP_CONFLICT));
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->render('admin/order/form.html.twig', [
					'entity' => $order,
					'form' => $form,
					'order_index_page' => null,
					...$this->getDraftDiscussionViewData(),
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
			...$this->getDraftDiscussionViewData(),
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('order.edit')]
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

		if ($form->isSubmitted() && $this->rejectStaleForm($form, $order, $this->concurrencyGuard)) {
			return $this->render('admin/order/form.html.twig', [
				'entity' => $order,
				'form' => $form,
				'order_index_page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
				...$this->getOrderDiscussionViewData($order),
			], new Response(status: Response::HTTP_CONFLICT));
		}

		if ($form->isSubmitted() && $form->isValid()) {
			$removedEntries = array_filter($originalEntries, static fn(OrderEntry $orderEntry) => !$order->getOrderEntries()->contains($orderEntry));

			try {
				$this->orderManager->saveOrder($order, $removedEntries);
			} catch (ConcurrencyConflictException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->render('admin/order/form.html.twig', [
					'entity' => $order,
					'form' => $form,
					'order_index_page' => $this->orderManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
					...$this->getOrderDiscussionViewData($order),
				], new Response(status: Response::HTTP_CONFLICT));
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
		$this->markOrderReadForCurrentUser($order);

		return $this->render('admin/order/show.html.twig', [
			'entity' => $order,
			...$this->getOrderDiscussionViewData($order),
			'query_params' => $request->query->all(),
			'can_ship' => $this->orderShipmentUseCase->hasShippableLines($order),
			'can_rollback_status' => $this->orderManager->canRollbackStatus($order),
			'rollback_target_status' => $this->orderManager->getRollbackTargetStatus($order)?->value,
			'payment_summary' => $this->orderPaymentReviewService->paymentSummary($order),
			'payment_review' => $this->orderPaymentReviewService->canceledPaidReview($order),
			'production_warehouses' => $this->warehouseRepository->findAvailableByStoreQB($store)->orderBy('warehouse.name', 'ASC')->getQuery()->getResult(),
		]);
	}

	#[Route('/{id}/entry/{entryId}/plan-production', name: 'plan_production', methods: ['POST'])]
	#[IsGranted('order.edit')]
	public function planProduction(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
		int $entryId,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$this->denyAccessUnlessGranted('production_order.create');

		if (!in_array($order->getStatus(), [OrderStatus::CONFIRMED, OrderStatus::AWAITING_STOCK], true)) {
			throw $this->createAccessDeniedException('Production demand can be planned only for confirmed orders awaiting fulfillment.');
		}

		if (!$this->isCsrfTokenValid('plan_production_order_entry_' . $entryId, (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		$entry = $this->orderManager->getEntityManager()->getRepository(OrderEntry::class)->find($entryId);
		$warehouse = $this->warehouseRepository->findOneBy([
			'id' => $request->request->getInt('warehouse_id'),
			'store' => $store,
		]);

		if (!$entry instanceof OrderEntry || $entry->getOrder() !== $order || !$warehouse) {
			throw $this->createNotFoundException();
		}

		try {
			$this->orderManager->getEntityManager()->wrapInTransaction(function () use ($entry, $order, $warehouse): void {
				$this->concurrencyGuard->lock($order);
				$entry
					->setFulfillmentSource(OrderEntryFulfillmentSource::PRODUCTION)
					->setWarehouse($warehouse);
				$this->orderManager->getEntityManager()->persist($entry);
				$user = $this->getUser();
				$context = $user instanceof User ? TransitionContext::manual($user) : TransitionContext::system();
				$this->productionDemandService->ensurePlannedDemand($entry, $context);
			});
			$this->addFlash('success', 'Production order planned.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (ProductionDemandException $exception) {
			$this->addFlash('danger', $this->translator->trans(
				$exception->getTranslationKey(),
				$exception->getTranslationParameters(),
			));

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/comments', name: 'comments', methods: ['GET'])]
	public function comments(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$this->markOrderReadForCurrentUser($order);

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

	#[Route('/{id}/customer-history', name: 'customer_history', methods: ['GET'])]
	#[IsGranted('customer.view')]
	public function customerHistory(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		$customer = $order->getCustomer();

		return $this->render('admin/order/customer_history.html.twig', [
			'entity' => $order,
			'customer' => $customer,
			'history' => $customer ? $this->customerHistoryProvider->forCustomer($customer) : null,
		]);
	}

	#[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
	#[IsGranted('order.confirm')]
	public function confirm(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('confirm_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->confirm($order);
			$this->addFlash('success', 'Order confirmed.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (ProductionDemandException $exception) {
			$this->addFlash('danger', $this->translator->trans(
				$exception->getTranslationKey(),
				$exception->getTranslationParameters(),
			));

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
	#[IsGranted('order.cancel')]
	public function cancel(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('cancel_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->cancel($order);
			$this->addFlash('success', 'Order canceled.');
			if ($this->orderPaymentReviewService->canceledPaidReview($order) !== null) {
				$this->addFlash('warning', 'Canceled order still has received payment. Review refund with accounting.');
			}
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/return-to-draft', name: 'return_to_draft', methods: ['POST'])]
	#[IsGranted('order.rollback')]
	public function returnToDraft(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('return_to_draft_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->returnToDraft($order);
			$this->addFlash('success', 'Order returned to draft.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/rollback-status', name: 'rollback_status', methods: ['POST'])]
	#[IsGranted('order.rollback')]
	public function rollbackStatus(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('rollback_status_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->rollbackStatus($order);
			$this->addFlash('success', 'Order status rolled back.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/mark-delivered', name: 'mark_delivered', methods: ['POST'])]
	#[IsGranted('order.mark_delivered')]
	public function markDelivered(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('mark_delivered_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->markDelivered($order);
			$this->addFlash('success', 'Order marked as delivered.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
	#[IsGranted('order.complete')]
	public function complete(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('complete_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->complete($order);
			$this->addFlash('success', 'Order completed.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/ship', name: 'ship', methods: ['POST'])]
	#[IsGranted('order.ship')]
	public function ship(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid('ship_order_' . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		$selectionSubmitted = $request->request->has('lines');
		$selection = $this->resolveShipmentSelection($request);

		try {
			$selectionSubmitted
				? $this->orderShipmentUseCase->createDraftForSelection($order, $selection)
				: $this->orderShipmentUseCase->createDraft($order);
			$this->addFlash('success', 'Order shipment draft created. Review and post the inventory document to ship stock.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/entry/{entryId}/return', name: 'entry_return', methods: ['POST'])]
	#[IsGranted('order.return')]
	public function entryReturn(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
		int $entryId,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$orderEntry = $this->findOrderEntry($order, $entryId);

		if (!$this->isCsrfTokenValid('return_order_entry_' . $order->getId() . '_' . $orderEntry->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->customerReturnUseCase->createDraft(
				$order,
				$orderEntry,
				$this->normalizeQuantity((string) $request->request->get('quantity')),
			);
			$this->addFlash('success', 'Customer return draft created. Review and post the inventory document to return stock.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/entry/{entryId}/refuse', name: 'entry_refuse', methods: ['POST'])]
	#[IsGranted('order.refuse')]
	public function entryRefuse(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Order $order,
		int $entryId,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$orderEntry = $this->findOrderEntry($order, $entryId);

		if (!$this->isCsrfTokenValid('refuse_order_entry_' . $order->getId() . '_' . $orderEntry->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Order action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$this->orderManager->refuseEntryRemaining(
				$order,
				$orderEntry,
				$this->normalizeQuantity((string) $request->request->get('quantity')),
			);
			$this->addFlash('success', 'Position refused. Reserved stock was released.');
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_CONFLICT);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	#[Route('/{id}/comment', name: 'comment_add', methods: ['POST'])]
	#[IsGranted('order.comment.manage')]
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
				$this->orderCommentManager->create(
					$order,
					$user,
					(string) $request->request->get('body'),
					$this->resolveCommentType($request),
					$request->request->getBoolean('important'),
				);
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
	#[IsGranted('order.comment.manage')]
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
				$this->orderCommentManager->edit(
					$comment,
					$user,
					(string) $request->request->get('body'),
					$this->resolveCommentType($request),
					$request->request->getBoolean('important'),
				);
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
	#[IsGranted('order.comment.manage')]
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
			'can_discount_override' => $this->isGranted(OrderEntryDiscountService::OVERRIDE_PERMISSION),
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
			$customer = new Customer()
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

		foreach ((array) $form->get('draftComments')->getData() as $comment) {
			if (!is_array($comment)) {
				continue;
			}

			$this->orderCommentManager->create(
				$order,
				$user,
				(string) ($comment['body'] ?? ''),
				CommentTypeEnum::tryFrom((string) ($comment['type'] ?? '')) ?? CommentTypeEnum::GENERAL,
				filter_var($comment['important'] ?? false, FILTER_VALIDATE_BOOL),
			);
		}
	}

	/**
	 * @return array{comment_templates: array, comment_types: array}
	 */
	private function getDraftDiscussionViewData(): array
	{
		return [
			'comment_templates' => $this->commentTemplateProvider->templates(),
			'comment_types' => CommentTypeEnum::cases(),
		];
	}

	private function denyOrderOutsideStore(Order $order, Store $store): void
	{
		if ($order->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}

	private function findOrderEntry(Order $order, int $entryId): OrderEntry
	{
		foreach ($order->getOrderEntries() as $orderEntry) {
			if ($orderEntry->getId() === $entryId) {
				return $orderEntry;
			}
		}

		throw $this->createNotFoundException();
	}

	/**
	 * Reads the per-position shipment selection (lines[entryId] = quantity) from the ship modal.
	 * Returns an empty array when no selection was submitted, which falls back to shipping everything.
	 *
	 * @return array<int, string>
	 */
	private function resolveShipmentSelection(Request $request): array
	{
		$selection = [];

		foreach ((array) $request->request->all('lines') as $entryId => $quantity) {
			if (!is_numeric($entryId)) {
				continue;
			}

			$normalized = $this->normalizeQuantity((string) $quantity);

			if ((float) $normalized > 0) {
				$selection[(int) $entryId] = $normalized;
			}
		}

		return $selection;
	}

	private function normalizeQuantity(string $quantity): string
	{
		return number_format((float) str_replace(',', '.', trim($quantity)), 4, '.', '');
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

	private function refreshOrderState(Order $order): void
	{
		$entityManager = $this->orderManager->getEntityManager();

		if ($entityManager->isOpen() && $entityManager->contains($order)) {
			$entityManager->refresh($order);
		}
	}

	private function quickActionResponse(Request $request, Store $store, Order $order, int $status = Response::HTTP_OK): Response
	{
		// On a failed action the transaction is rolled back, but the in-memory order still holds the
		// mutated status set by the workflow transition. Reload it from the database so the rendered
		// card/row fragments reflect the real (unchanged) status instead of the optimistic one.
		if ($status >= Response::HTTP_BAD_REQUEST) {
			$this->refreshOrderState($order);
		}

		$entityManagerIsOpen = $this->orderManager->getEntityManager()->isOpen();

		if (!$this->wantsQuickActionJson($request) && !$entityManagerIsOpen && $status >= Response::HTTP_BAD_REQUEST) {
			return $this->redirectToRoute('app_admin_order_index', [
				'store_id' => $store->getId(),
				'id' => $order->getId(),
				'page' => max(1, $request->query->getInt('page', 1)),
			]);
		}

		if (!$this->wantsQuickActionJson($request)) {
			return $this->redirectToOrderIndex($store, $order);
		}

		if (!$entityManagerIsOpen && $status >= Response::HTTP_BAD_REQUEST) {
			return $this->quickActionJsonResponse($request, [], $status);
		}

		return $this->quickActionJsonResponse($request, [
			'card' => $this->renderView('admin/order/show.html.twig', [
				'entity' => $order,
				'query_params' => $request->query->all(),
				'can_ship' => $this->orderShipmentUseCase->hasShippableLines($order),
				'can_rollback_status' => $this->orderManager->canRollbackStatus($order),
				'rollback_target_status' => $this->orderManager->getRollbackTargetStatus($order)?->value,
				'payment_summary' => $this->orderPaymentReviewService->paymentSummary($order),
				'payment_review' => $this->orderPaymentReviewService->canceledPaidReview($order),
				'production_warehouses' => $this->warehouseRepository->findAvailableByStoreQB($store)->orderBy('warehouse.name', 'ASC')->getQuery()->getResult(),
			]),
			'history' => $this->renderView('admin/order/history.html.twig', [
				'entity' => $order,
				'history_entries' => $this->documentTimelineBuilder->forOrder($order),
			]),
			'payments' => $this->paymentDocumentRenderer->renderOrder($order, $store, $request),
			'row' => $this->renderView('admin/order/_index_row.html.twig', [
				'entity' => $order,
				'first_entity' => $order,
				'unread_comment_counts' => $this->unreadCommentCounts([$order], $this->getUser()),
			]),
		], $status);
	}

	/**
	 * @return array{comments: array, history_entries: array, comment_templates: array, comment_types: array}
	 */
	private function getOrderDiscussionViewData(Order $order): array
	{
		return [
			'comments' => $this->orderCommentManager->getRepository()->findVisibleByOrder($order),
			'history_entries' => $this->documentTimelineBuilder->forOrder($order),
			'comment_templates' => $this->commentTemplateProvider->templates(),
			'comment_types' => CommentTypeEnum::cases(),
		];
	}

	private function resolveCommentType(Request $request): CommentTypeEnum
	{
		return CommentTypeEnum::tryFrom((string) $request->request->get('type')) ?? CommentTypeEnum::GENERAL;
	}

	private function markOrderReadForCurrentUser(Order $order): void
	{
		$user = $this->getUser();

		if ($user instanceof User) {
			$this->orderCommentManager->markOrderRead($order, $user);
		}
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

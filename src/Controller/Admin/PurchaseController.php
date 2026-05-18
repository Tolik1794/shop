<?php

namespace App\Controller\Admin;

use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Form\Admin\FilterType\PurchaseFilterType;
use App\Form\Admin\Type\PurchaseType;
use App\Manager\PurchaseManager;
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

#[Route('/admin/store/{store_id}/purchase', name: 'app_admin_purchase_'), IsGranted('ROLE_STORE_ADMIN')]
class PurchaseController extends AbstractAdvancedController
{
	private const int DEFAULT_PAGE_LIMIT = 20;

	public function __construct(private readonly PurchaseManager $purchaseManager)
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
		$queryBuilder = $this->purchaseManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(PurchaseFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['purchase.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$purchase = $this->purchaseManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
			]);
		} else {
			$purchase = $pagination->current();
		}

		return $this->render('admin/purchase/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $purchase,
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
		$purchase = $this->purchaseManager->createDraft($store);
		$form = $this->createPurchaseForm($purchase, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$this->purchaseManager->savePurchase($purchase);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->renderForm($purchase, $form, null, Response::HTTP_UNPROCESSABLE_ENTITY);
			}

			return $this->stayOrRedirect(
				route: 'app_admin_purchase_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_purchase_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $purchase->getId()],
			);
		}

		return $this->renderForm(
			$purchase,
			$form,
			null,
			$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
		);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Purchase $purchase,
	): Response
	{
		$this->denyPurchaseOutsideStore($purchase, $store);
		$this->denyNotDraft($purchase);

		$originalEntries = [];
		foreach ($purchase->getPurchaseEntries() as $purchaseEntry) {
			$originalEntries[$purchaseEntry->getId()] = $purchaseEntry;
		}

		$form = $this->createPurchaseForm($purchase, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$removedEntries = array_filter(
				$originalEntries,
				static fn (PurchaseEntry $purchaseEntry): bool => !$purchase->getPurchaseEntries()->contains($purchaseEntry),
			);

			try {
				$this->purchaseManager->savePurchase($purchase, $removedEntries);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->renderForm(
					$purchase,
					$form,
					$this->purchaseManager->getRepository()->getIndexPage($purchase, self::DEFAULT_PAGE_LIMIT),
					Response::HTTP_UNPROCESSABLE_ENTITY,
				);
			}

			return $this->stayOrRedirect('app_admin_purchase_index', ['store_id' => $store->getId()]);
		}

		return $this->renderForm(
			$purchase,
			$form,
			$this->purchaseManager->getRepository()->getIndexPage($purchase, self::DEFAULT_PAGE_LIMIT),
			$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
		);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Purchase $purchase,
	): Response
	{
		$this->denyPurchaseOutsideStore($purchase, $store);

		return $this->render('admin/purchase/show.html.twig', [
			'entity' => $purchase,
			'query_params' => array_filter($request->query->all(), static fn ($value) => is_scalar($value)),
		]);
	}

	#[Route('/{id}/order', name: 'order', methods: ['POST'])]
	public function order(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Purchase $purchase,
	): Response
	{
		$this->denyPurchaseOutsideStore($purchase, $store);

		if ($this->isCsrfTokenValid('order_purchase_' . $purchase->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->purchaseManager->order($purchase);
			} catch (RuntimeException) {
			}
		}

		return $this->redirectToPurchaseIndex($store, $purchase);
	}

	#[Route('/{id}/return-to-draft', name: 'return_to_draft', methods: ['POST'])]
	public function returnToDraft(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Purchase $purchase,
	): Response
	{
		$this->denyPurchaseOutsideStore($purchase, $store);

		if ($this->isCsrfTokenValid('return_to_draft_purchase_' . $purchase->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->purchaseManager->returnToDraft($purchase);
			} catch (RuntimeException) {
			}
		}

		return $this->redirectToPurchaseIndex($store, $purchase);
	}

	#[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
	public function cancel(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Purchase $purchase,
	): Response
	{
		$this->denyPurchaseOutsideStore($purchase, $store);

		if ($this->isCsrfTokenValid('cancel_purchase_' . $purchase->getId(), (string) $request->request->get('_token'))) {
			$this->purchaseManager->cancel($purchase);
		}

		return $this->redirectToPurchaseIndex($store, $purchase);
	}

	private function createPurchaseForm(Purchase $purchase, Store $store): FormInterface
	{
		return $this->createForm(PurchaseType::class, $purchase, [
			'method' => 'POST',
			'store' => $store,
			'product_ajax_url' => $this->generateUrl('app_api_admin_purchase_search_products_for_purchase_entry', [
				'store_id' => $store->getId(),
			]),
			'attr' => [
				'data-select-two-target' => 'form',
			],
		]);
	}

	private function renderForm(Purchase $purchase, FormInterface $form, ?int $purchaseIndexPage, int $status): Response
	{
		$response = $this->render('admin/purchase/form.html.twig', [
			'entity' => $purchase,
			'form' => $form,
			'purchase_index_page' => $purchaseIndexPage,
		]);
		$response->setStatusCode($status);

		return $response;
	}

	private function denyPurchaseOutsideStore(Purchase $purchase, Store $store): void
	{
		if ($purchase->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}

	private function denyNotDraft(Purchase $purchase): void
	{
		if ($purchase->getStatus() !== PurchaseStatus::DRAFT) {
			throw $this->createAccessDeniedException('Only draft purchase can be changed.');
		}
	}

	private function redirectToPurchaseIndex(Store $store, Purchase $purchase): Response
	{
		return $this->redirectToRoute('app_admin_purchase_index', [
			'store_id' => $store->getId(),
			'id' => $purchase->getId(),
			'page' => $this->purchaseManager->getRepository()->getIndexPage($purchase, self::DEFAULT_PAGE_LIMIT),
		]);
	}
}

<?php

namespace App\Controller\Admin;

use App\Entity\InventoryDocument;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Form\Admin\FilterType\InventoryDocumentFilterType;
use App\Repository\InventoryDocumentRepository;
use App\Repository\StatusHistoryRepository;
use App\Service\FilterFormHandler;
use App\Service\InventoryPostingService;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/inventory-document', name: 'app_admin_inventory_document_'), IsGranted('ROLE_STORE_ADMIN')]
class InventoryDocumentController extends AbstractAdvancedController
{
	use QuickActionResponseTrait;

	public function __construct(
		private readonly InventoryDocumentRepository $inventoryDocumentRepository,
		private readonly InventoryPostingService $inventoryPostingService,
		private readonly StatusHistoryRepository $statusHistoryRepository,
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
		$queryBuilder = $this->inventoryDocumentRepository->findAvailableByStoreQB($store);
		$filterForm = $this->createForm(InventoryDocumentFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['inventoryDocument.documentDate', 'inventoryDocument.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$inventoryDocument = $this->inventoryDocumentRepository->findOneBy([
				'id' => $id,
				'store' => $store,
			]);
		} else {
			$inventoryDocument = $pagination->current();
		}

		return $this->render('admin/inventory_document/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $inventoryDocument,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryDocument $inventoryDocument,
	): Response
	{
		$inventoryDocument = $this->findInventoryDocumentForStore($store, (int) $inventoryDocument->getId());

		return $this->render('admin/inventory_document/show.html.twig', [
			'entity' => $inventoryDocument,
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/history', name: 'history', methods: ['GET'])]
	public function history(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryDocument $inventoryDocument,
	): Response
	{
		$this->denyInventoryDocumentOutsideStore($inventoryDocument, $store);

		return $this->render('admin/inventory_document/history.html.twig', [
			'entity' => $inventoryDocument,
			'status_history_entries' => $this->statusHistoryRepository->findTimelineFor($store, StatusHistoryEntityType::INVENTORY_DOCUMENT, (int) $inventoryDocument->getId()),
		]);
	}

	#[Route('/{id}/post', name: 'post', methods: ['POST'])]
	public function post(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryDocument $inventoryDocument,
	): Response
	{
		return $this->applyAction($request, $store, $inventoryDocument, 'post_inventory_document_', function () use ($inventoryDocument): void {
			$this->inventoryPostingService->post($inventoryDocument);
		}, 'Inventory document posted.');
	}

	#[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
	public function cancel(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryDocument $inventoryDocument,
	): Response
	{
		return $this->applyAction($request, $store, $inventoryDocument, 'cancel_inventory_document_', function () use ($inventoryDocument): void {
			$this->inventoryPostingService->cancel($inventoryDocument);
		}, 'Inventory document canceled.');
	}

	private function applyAction(
		Request $request,
		Store $store,
		InventoryDocument $inventoryDocument,
		string $csrfPrefix,
		callable $action,
		string $successMessage,
	): Response
	{
		$this->denyInventoryDocumentOutsideStore($inventoryDocument, $store);

		if (!$this->isCsrfTokenValid($csrfPrefix . $inventoryDocument->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Inventory document action token is invalid.');

			return $this->quickActionResponse($request, $store, $inventoryDocument, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$action();
			$this->addFlash('success', $successMessage);
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $inventoryDocument, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $inventoryDocument);
	}

	private function findInventoryDocumentForStore(Store $store, int $id): InventoryDocument
	{
		$inventoryDocument = $this->inventoryDocumentRepository->findOneForStoreWithDetails($store, $id);

		if (!$inventoryDocument instanceof InventoryDocument) {
			throw $this->createNotFoundException();
		}

		return $inventoryDocument;
	}

	private function denyInventoryDocumentOutsideStore(InventoryDocument $inventoryDocument, Store $store): void
	{
		if ($inventoryDocument->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}

	private function redirectToInventoryDocumentIndex(Store $store, InventoryDocument $inventoryDocument): Response
	{
		return $this->redirectToRoute('app_admin_inventory_document_index', [
			'store_id' => $store->getId(),
			'id' => $inventoryDocument->getId(),
		]);
	}

	private function quickActionResponse(Request $request, Store $store, InventoryDocument $inventoryDocument, int $status = Response::HTTP_OK): Response
	{
		if (!$this->wantsQuickActionJson($request)) {
			return $this->redirectToInventoryDocumentIndex($store, $inventoryDocument);
		}

		$inventoryDocument = $this->findInventoryDocumentForStore($store, (int) $inventoryDocument->getId());

		return $this->quickActionJsonResponse($request, [
			'card' => $this->renderView('admin/inventory_document/show.html.twig', [
				'entity' => $inventoryDocument,
				'query_params' => $request->query->all(),
			]),
			'history' => $this->renderView('admin/inventory_document/history.html.twig', [
				'entity' => $inventoryDocument,
				'status_history_entries' => $this->statusHistoryRepository->findTimelineFor($store, StatusHistoryEntityType::INVENTORY_DOCUMENT, (int) $inventoryDocument->getId()),
			]),
			'row' => $this->renderView('admin/inventory_document/_index_row.html.twig', [
				'entity' => $inventoryDocument,
				'first_entity' => $inventoryDocument,
			]),
		], $status);
	}
}

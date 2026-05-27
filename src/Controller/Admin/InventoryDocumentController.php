<?php

namespace App\Controller\Admin;

use App\Dto\Admin\Inventory\InventoryCustomerReturnOperation;
use App\Dto\Admin\Inventory\InventoryStockAdjustmentOperation;
use App\Dto\Admin\Inventory\InventorySupplierReturnOperation;
use App\Dto\Admin\Inventory\InventoryTransferOperation;
use App\Dto\Admin\Inventory\InventoryWriteOffOperation;
use App\Entity\InventoryDocument;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Exception\ConcurrencyConflictException;
use App\Form\Admin\FilterType\InventoryDocumentFilterType;
use App\Form\Admin\Type\InventoryCustomerReturnOperationType;
use App\Form\Admin\Type\InventoryStockAdjustmentOperationType;
use App\Form\Admin\Type\InventorySupplierReturnOperationType;
use App\Form\Admin\Type\InventoryTransferOperationType;
use App\Form\Admin\Type\InventoryWriteOffOperationType;
use App\Repository\InventoryDocumentRepository;
use App\Repository\StatusHistoryRepository;
use App\Service\FilterFormHandler;
use App\Service\Inventory\CustomerReturnUseCase;
use App\Service\Inventory\InventoryTransferUseCase;
use App\Service\Inventory\SupplierReturnUseCase;
use App\Service\Inventory\WriteOffAdjustmentUseCase;
use App\Service\InventoryPostingService;
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

#[Route('/admin/store/{store_id}/inventory-document', name: 'app_admin_inventory_document_'), IsGranted('inventory_document.view')]
class InventoryDocumentController extends AbstractAdvancedController
{
	use QuickActionResponseTrait;

	public function __construct(
		private readonly InventoryDocumentRepository $inventoryDocumentRepository,
		private readonly InventoryPostingService $inventoryPostingService,
		private readonly StatusHistoryRepository $statusHistoryRepository,
		private readonly WriteOffAdjustmentUseCase $writeOffAdjustmentUseCase,
		private readonly InventoryTransferUseCase $inventoryTransferUseCase,
		private readonly CustomerReturnUseCase $customerReturnUseCase,
		private readonly SupplierReturnUseCase $supplierReturnUseCase,
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

	#[Route('/write-off/new', name: 'write_off_new', methods: ['GET', 'POST'])]
	#[IsGranted('inventory_document.create')]
	public function newWriteOff(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$operation = new InventoryWriteOffOperation();
		$form = $this->createForm(InventoryWriteOffOperationType::class, $operation, ['store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$document = $this->writeOffAdjustmentUseCase->createWriteOffDraft(
					$store,
					$operation->product,
					$operation->warehouse,
					$this->decimalString($operation->quantity),
					$operation->reason,
					$this->blankToNull($operation->number),
				);
				$this->addFlash('success', 'Write-off draft created.');

				return $this->redirectToInventoryDocumentIndex($store, $document);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}
		}

		return $this->renderOperationForm($form, 'New write-off', 'Create write-off draft', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
	}

	#[Route('/stock-adjustment/new', name: 'stock_adjustment_new', methods: ['GET', 'POST'])]
	#[IsGranted('inventory_document.create')]
	public function newStockAdjustment(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$operation = new InventoryStockAdjustmentOperation();
		$form = $this->createForm(InventoryStockAdjustmentOperationType::class, $operation, ['store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$lines = [];

				foreach ($operation->lines as $line) {
					$lines[] = [
						'product' => $line->product,
						'warehouse' => $line->warehouse,
						'direction' => $line->direction,
						'quantity' => $this->decimalString($line->quantity),
						'unitCost' => $this->optionalDecimalString($line->unitCost),
					];
				}

				$document = $this->writeOffAdjustmentUseCase->createStockAdjustmentDraft(
					$store,
					$operation->reason,
					$lines,
					$this->blankToNull($operation->number),
				);
				$this->addFlash('success', 'Stock adjustment draft created.');

				return $this->redirectToInventoryDocumentIndex($store, $document);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}
		}

		return $this->renderOperationForm($form, 'New stock adjustment', 'Create adjustment draft', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
	}

	#[Route('/transfer/new', name: 'transfer_new', methods: ['GET', 'POST'])]
	#[IsGranted('inventory_document.create')]
	public function newTransfer(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$operation = new InventoryTransferOperation();
		$form = $this->createForm(InventoryTransferOperationType::class, $operation, ['store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$document = $this->inventoryTransferUseCase->createDraft(
					$store,
					$operation->product,
					$operation->sourceWarehouse,
					$operation->destinationWarehouse,
					$this->decimalString($operation->quantity),
					$operation->reason,
					$this->blankToNull($operation->number),
				);
				$this->addFlash('success', 'Transfer draft created.');

				return $this->redirectToInventoryDocumentIndex($store, $document);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}
		}

		return $this->renderOperationForm($form, 'New transfer', 'Create transfer draft', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
	}

	#[Route('/customer-return/new', name: 'customer_return_new', methods: ['GET', 'POST'])]
	#[IsGranted('inventory_document.create')]
	public function newCustomerReturn(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$operation = new InventoryCustomerReturnOperation();
		$form = $this->createForm(InventoryCustomerReturnOperationType::class, $operation, ['store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$quantity = $this->decimalString($operation->quantity);
			$available = $this->returnableQuantity($operation->orderEntry?->getShippedQuantity(), $operation->orderEntry?->getReturnedQuantity());

			if ((float) $quantity > (float) $available) {
				$form->get('quantity')->addError(new FormError('Quantity cannot exceed available return quantity.'));
			} else {
				try {
					$document = $this->customerReturnUseCase->createDraft(
						$operation->orderEntry->getOrder(),
						$operation->orderEntry,
						$quantity,
						$operation->reason,
						$this->blankToNull($operation->number),
					);
					$this->addFlash('success', 'Customer return draft created.');

					return $this->redirectToInventoryDocumentIndex($store, $document);
				} catch (RuntimeException $exception) {
					$form->addError(new FormError($exception->getMessage()));
				}
			}
		}

		return $this->renderOperationForm($form, 'New customer return', 'Create customer return draft', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
	}

	#[Route('/supplier-return/new', name: 'supplier_return_new', methods: ['GET', 'POST'])]
	#[IsGranted('inventory_document.create')]
	public function newSupplierReturn(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$operation = new InventorySupplierReturnOperation();
		$form = $this->createForm(InventorySupplierReturnOperationType::class, $operation, ['store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$quantity = $this->decimalString($operation->quantity);
			$available = $this->returnableQuantity($operation->purchaseEntry?->getReceivedQuantity(), $operation->purchaseEntry?->getReturnedQuantity());

			if ((float) $quantity > (float) $available) {
				$form->get('quantity')->addError(new FormError('Quantity cannot exceed available return quantity.'));
			} else {
				try {
					$document = $this->supplierReturnUseCase->createDraft(
						$operation->purchaseEntry->getPurchase(),
						$operation->purchaseEntry,
						$quantity,
						$operation->reason,
						$this->blankToNull($operation->number),
					);
					$this->addFlash('success', 'Supplier return draft created.');

					return $this->redirectToInventoryDocumentIndex($store, $document);
				} catch (RuntimeException $exception) {
					$form->addError(new FormError($exception->getMessage()));
				}
			}
		}

		return $this->renderOperationForm($form, 'New supplier return', 'Create supplier return draft', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
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
	#[IsGranted('inventory_document.post')]
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
	#[IsGranted('inventory_document.cancel')]
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
		} catch (ConcurrencyConflictException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $inventoryDocument, Response::HTTP_CONFLICT);
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

	private function renderOperationForm(FormInterface $form, string $title, string $submitLabel, int $status): Response
	{
		return $this->render('admin/inventory_document/operation_form.html.twig', [
			'form' => $form,
			'title' => $title,
			'submit_label' => $submitLabel,
		], new Response(status: $status));
	}

	private function decimalString(string|float|int|null $value): string
	{
		return number_format((float) str_replace(',', '.', (string) $value), 4, '.', '');
	}

	private function optionalDecimalString(string|float|int|null $value): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}

		return $this->decimalString($value);
	}

	private function blankToNull(?string $value): ?string
	{
		$value = trim((string) $value);

		return $value === '' ? null : $value;
	}

	private function returnableQuantity(?string $completed, ?string $returned): string
	{
		return number_format(max(0, (float) ($completed ?? '0') - (float) ($returned ?? '0')), 4, '.', '');
	}
}

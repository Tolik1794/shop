<?php

namespace App\Controller\Admin;

use App\Entity\Store;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Form\Admin\FilterType\WarehouseStockFilterType;
use App\Form\Admin\Type\WarehouseStockType;
use App\Manager\WarehouseManager;
use App\Manager\WarehouseStockManager;
use App\Repository\WarehouseStockBatchRepository;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use App\Validator\WarehouseStockBusinessValidator;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/warehouse/stock', name: 'app_admin_warehouse_stock_'), IsGranted('ROLE_STORE_ADMIN')]
class WarehouseStockController extends AbstractAdvancedController
{
	private const DEFAULT_PAGE_LIMIT = 20;

	public function __construct(
		private readonly WarehouseManager $warehouseManager,
		private readonly WarehouseStockManager $warehouseStockManager,
		private readonly WarehouseStockBusinessValidator $warehouseStockBusinessValidator,
		private readonly WarehouseStockBatchRepository $warehouseStockBatchRepository,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		FilterFormHandler $filterTypeHandler,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$warehouse = $this->resolveWarehouse($request, $store);
		$queryBuilder = $this->warehouseStockManager
			->getRepository()
			->findAvailableByWarehouseQB($warehouse);

		$filterForm = $this->createForm(WarehouseStockFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['product.name', 'warehouseStock.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$warehouseStock = $this->warehouseStockManager->getRepository()->findOneBy([
				'id' => $id,
				'warehouse' => $warehouse,
			]);
		} else {
			$warehouseStock = $pagination->current();
		}

		return $this->render('admin/warehouse_stock/index.html.twig', [
			'warehouse' => $warehouse,
			'warehouse_index_page' => $this->warehouseManager->getRepository()->getIndexPage($warehouse, self::DEFAULT_PAGE_LIMIT),
			'pagination' => $pagination,
			'first_entity' => $warehouseStock,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$warehouse = $this->resolveWarehouse($request, $store);
		$warehouseStock = new WarehouseStock();
		$warehouseStock->setWarehouse($warehouse);

		$form = $this->createForm(WarehouseStockType::class, $warehouseStock, [
			'method' => 'POST',
			'store' => $store,
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
			'product_ajax_url' => $this->generateUrl('app_api_admin_warehouse_stock_search_products_for_warehouse_stock', [
				'store_id' => $store->getId(),
				'warehouse_id' => $warehouse->getId(),
			]),
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->warehouseStockBusinessValidator->validate($warehouseStock, $form) && $form->isValid()) {
			$this->warehouseStockManager->save($warehouseStock);

			return $this->stayOrRedirect(
				route: 'app_admin_warehouse_stock_index',
				parameters: ['store_id' => $store->getId(), 'warehouse_id' => $warehouse->getId()],
				stayRoute: 'app_admin_warehouse_stock_edit',
				stayParameters: ['store_id' => $store->getId(), 'warehouse_id' => $warehouse->getId(), 'id' => $warehouseStock->getId()],
			);
		}

		return $this->render('admin/warehouse_stock/form.html.twig', [
			'warehouse' => $warehouse,
			'warehouse_index_page' => $this->warehouseManager->getRepository()->getIndexPage($warehouse, self::DEFAULT_PAGE_LIMIT),
			'warehouse_stock_index_page' => $warehouseStock->getId() ? $this->warehouseStockManager->getRepository()->getIndexPage($warehouseStock, self::DEFAULT_PAGE_LIMIT) : null,
			'entity' => $warehouseStock,
			'form' => $form
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		WarehouseStock $warehouseStock,
	): Response
	{
		$warehouse = $warehouseStock->getWarehouse();

		if (!$warehouse instanceof Warehouse || $warehouse->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		$form = $this->createForm(WarehouseStockType::class, $warehouseStock, [
			'method' => 'POST',
			'store' => $store,
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
			'product_ajax_url' => $this->generateUrl('app_api_admin_warehouse_stock_search_products_for_warehouse_stock', [
				'store_id' => $store->getId(),
				'warehouse_id' => $warehouse->getId(),
			]),
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->warehouseStockBusinessValidator->validate($warehouseStock, $form) && $form->isValid()) {
			$this->warehouseStockManager->save($warehouseStock);

			return $this->stayOrRedirect('app_admin_warehouse_stock_index', [
				'store_id' => $store->getId(),
				'warehouse_id' => $warehouse->getId(),
			]);
		}

		return $this->render('admin/warehouse_stock/form.html.twig', [
			'warehouse' => $warehouse,
			'warehouse_index_page' => $this->warehouseManager->getRepository()->getIndexPage($warehouse, self::DEFAULT_PAGE_LIMIT),
			'warehouse_stock_index_page' => $this->warehouseStockManager->getRepository()->getIndexPage($warehouseStock, self::DEFAULT_PAGE_LIMIT),
			'entity' => $warehouseStock,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		WarehouseStock $warehouseStock,
	): Response
	{
		if ($warehouseStock->getWarehouse()?->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		return $this->render('admin/warehouse_stock/show.html.twig', [
			'entity' => $warehouseStock,
			'batches' => $this->warehouseStockBatchRepository->findByWarehouseStock($warehouseStock),
			'query_params' => $request->query->all()
		]);
	}

	private function resolveWarehouse(Request $request, Store $store): Warehouse
	{
		$warehouseId = $request->query->getInt('warehouse_id');
		$warehouse = $warehouseId
			? $this->warehouseManager->getRepository()->findOneBy(['id' => $warehouseId, 'store' => $store])
			: $this->warehouseManager->getRepository()->findOneBy(['store' => $store], ['name' => 'ASC']);

		if (!$warehouse instanceof Warehouse) {
			throw $this->createNotFoundException();
		}

		return $warehouse;
	}

}

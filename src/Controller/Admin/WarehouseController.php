<?php

namespace App\Controller\Admin;

use App\Entity\Store;
use App\Entity\Warehouse;
use App\Form\Admin\FilterType\WarehouseFilterType;
use App\Form\Admin\Type\WarehouseType;
use App\Manager\WarehouseManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/warehouse', name: 'app_admin_warehouse_'), IsGranted('warehouse.view')]
class WarehouseController extends AbstractAdvancedController
{
	public function __construct(private readonly WarehouseManager $warehouseManager)
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
		$queryBuilder = $this->warehouseManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(WarehouseFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['warehouse.name', 'warehouse.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$warehouse = $this->warehouseManager->getRepository()->find($id);
		} else {
			$warehouse = $pagination->current();
		}

		return $this->render('admin/warehouse/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $warehouse,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[IsGranted('warehouse.manage')]
	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$warehouse = new Warehouse();
		$warehouse->setStore($store);
		$form = $this->createForm(WarehouseType::class, $warehouse, [
			'method' => 'POST',
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->warehouseManager->save($warehouse);

			return $this->stayOrRedirect(
				route: 'app_admin_warehouse_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_warehouse_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $warehouse->getId()],
			);
		}

		return $this->render('admin/warehouse/form.html.twig', [
			'entity' => $warehouse,
			'form' => $form
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('warehouse.manage')]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Warehouse $warehouse,
	): Response
	{
		$form = $this->createForm(WarehouseType::class, $warehouse, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->warehouseManager->save($warehouse);

			return $this->stayOrRedirect('app_admin_warehouse_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/warehouse/form.html.twig', [
			'entity' => $warehouse,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(Request $request, Warehouse $warehouse): Response
	{
		return $this->render('admin/warehouse/show.html.twig', [
			'entity' => $warehouse,
			'query_params' => $request->query->all()
		]);
	}
}

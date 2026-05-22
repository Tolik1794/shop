<?php

namespace App\Controller\Admin;

use App\Entity\InventoryReason;
use App\Entity\Store;
use App\Form\Admin\FilterType\InventoryReasonFilterType;
use App\Form\Admin\Type\InventoryReasonType;
use App\Manager\InventoryReasonManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/inventory-reason', name: 'app_admin_inventory_reason_'), IsGranted('ROLE_STORE_ADMIN')]
class InventoryReasonController extends AbstractAdvancedController
{
	public function __construct(private readonly InventoryReasonManager $inventoryReasonManager)
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
		$queryBuilder = $this->inventoryReasonManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(InventoryReasonFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['inventoryReason.type', 'inventoryReason.name', 'inventoryReason.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$inventoryReason = $this->inventoryReasonManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
				'deletedAt' => null,
			]);
		} else {
			$inventoryReason = $pagination->current();
		}

		return $this->render('admin/inventory_reason/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $inventoryReason,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$inventoryReason = new InventoryReason();
		$inventoryReason->setStore($store);

		$form = $this->createForm(InventoryReasonType::class, $inventoryReason, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->inventoryReasonManager->save($inventoryReason);

			return $this->stayOrRedirect(
				route: 'app_admin_inventory_reason_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_inventory_reason_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $inventoryReason->getId()],
			);
		}

		return $this->render('admin/inventory_reason/form.html.twig', [
			'entity' => $inventoryReason,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryReason $inventoryReason,
	): Response {
		$this->denyInventoryReasonOutsideStore($inventoryReason, $store);

		$form = $this->createForm(InventoryReasonType::class, $inventoryReason, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->inventoryReasonManager->save($inventoryReason);

			return $this->stayOrRedirect('app_admin_inventory_reason_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/inventory_reason/form.html.twig', [
			'entity' => $inventoryReason,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryReason $inventoryReason,
	): Response {
		$this->denyInventoryReasonOutsideStore($inventoryReason, $store);

		return $this->render('admin/inventory_reason/show.html.twig', [
			'entity' => $inventoryReason,
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
	public function archive(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		InventoryReason $inventoryReason,
	): Response {
		$this->denyInventoryReasonOutsideStore($inventoryReason, $store);

		if ($this->isCsrfTokenValid('archive_inventory_reason_' . $inventoryReason->getId(), (string) $request->request->get('_token'))) {
			$this->inventoryReasonManager->archive($inventoryReason);
		}

		return $this->redirectToRoute('app_admin_inventory_reason_index', ['store_id' => $store->getId()]);
	}

	private function denyInventoryReasonOutsideStore(InventoryReason $inventoryReason, Store $store): void
	{
		if ($inventoryReason->getStore()?->getId() !== $store->getId() || $inventoryReason->getDeletedAt() !== null) {
			throw $this->createNotFoundException();
		}
	}
}

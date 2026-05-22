<?php

namespace App\Controller\Admin;

use App\Entity\Store;
use App\Entity\Unit;
use App\Form\Admin\FilterType\UnitFilterType;
use App\Form\Admin\Type\UnitType;
use App\Manager\UnitManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/unit', name: 'app_admin_unit_'), IsGranted('ROLE_STORE_ADMIN')]
class UnitController extends AbstractAdvancedController
{
	public function __construct(private readonly UnitManager $unitManager)
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
		$queryBuilder = $this->unitManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(UnitFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['unit.name', 'unit.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$unit = $this->unitManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
				'deletedAt' => null,
			]);
		} else {
			$unit = $pagination->current();
		}

		return $this->render('admin/unit/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $unit,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$unit = new Unit();
		$unit->setStore($store);

		$form = $this->createForm(UnitType::class, $unit, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->unitManager->save($unit);

			return $this->stayOrRedirect(
				route: 'app_admin_unit_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_unit_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $unit->getId()],
			);
		}

		return $this->render('admin/unit/form.html.twig', [
			'entity' => $unit,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Unit $unit,
	): Response {
		$this->denyUnitOutsideStore($unit, $store);

		$form = $this->createForm(UnitType::class, $unit, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->unitManager->save($unit);

			return $this->stayOrRedirect('app_admin_unit_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/unit/form.html.twig', [
			'entity' => $unit,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Unit $unit,
	): Response {
		$this->denyUnitOutsideStore($unit, $store);

		return $this->render('admin/unit/show.html.twig', [
			'entity' => $unit,
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
	public function archive(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Unit $unit,
	): Response {
		$this->denyUnitOutsideStore($unit, $store);

		if ($this->isCsrfTokenValid('archive_unit_' . $unit->getId(), (string) $request->request->get('_token'))) {
			$this->unitManager->archive($unit);
		}

		return $this->redirectToRoute('app_admin_unit_index', ['store_id' => $store->getId()]);
	}

	private function denyUnitOutsideStore(Unit $unit, Store $store): void
	{
		if ($unit->getStore()?->getId() !== $store->getId() || $unit->getDeletedAt() !== null) {
			throw $this->createNotFoundException();
		}
	}
}

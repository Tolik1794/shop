<?php

namespace App\Controller\Admin;

use App\Entity\Store;
use App\Form\Admin\FilterType\StoreFilterType;
use App\Form\Admin\Type\StoreType;
use App\Manager\StoreManager;
use App\Security\Voter\StoreVoter;
use App\Service\Dashboard\StoreDashboardProvider;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use DateTimeImmutable;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store', name: 'admin_store_'), IsGranted('store.view')]
class StoreController extends AbstractAdvancedController
{
	public function __construct(private readonly StoreManager $storeManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(PaginatorInterface $paginator, Request $request, FilterFormHandler $filterTypeHandler): Response
	{
		$queryBuilder = $this->storeManager
			->getRepository()
			->findAvailableStoresQB($this->getUser());

		$filterForm = $this->createForm(StoreFilterType::class);
		$filterForm->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['store.name'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($storeId = $request->query->get('store_id')) {
			$store = $this->storeManager->getRepository()->find($storeId);
		} else {
			$store = $pagination->current();
		}

		return $this->render('admin/store/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $store,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[IsGranted('store.create')]
	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request): Response
	{
		$store = new Store();
		$form = $this->createForm(StoreType::class, $store, [
			'method' => 'POST',
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->storeManager->save($store);

			return $this->stayOrRedirect(
				route: 'admin_store_index',
				stayRoute: 'admin_store_edit',
				stayParameters: ['store_id' => $store->getId()],
			);
		}

		return $this->render('admin/store/form.html.twig', [
			'entity' => $store,
			'form' => $form,
			'avatar' => $this->storeManager->getAvatar($store)?->getPathname()
		]);
	}

	#[Route('/{store_id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$this->denyAccessUnlessGranted(StoreVoter::EDIT, $store);
		$form = $this->createForm(StoreType::class, $store, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			if ($avatar = $form->get('avatar')->getData()) $this->storeManager->updateAvatar($store, $avatar);
			$this->storeManager->save($store);

			return $this->stayOrRedirect('admin_store_index');
		}

		return $this->render('admin/store/form.html.twig', [
			'entity' => $store,
			'form' => $form,
			'avatar' => $this->storeManager->getAvatar($store)?->getPathname()
		]);
	}

	#[Route('/{store_id}/show', name: 'show')]
	public function show(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$this->denyAccessUnlessGranted(StoreVoter::VIEW, $store);

		return $this->render('admin/store/show.html.twig', [
			'entity' => $store,
			'avatar' => $this->storeManager->getAvatar($store)?->getPathname(),
			'query_params' => $request->query->all()
		]);
	}

	#[Route('/{store_id}/main', name: 'main')]
	#[IsGranted('dashboard.view')]
	public function main(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, StoreDashboardProvider $dashboardProvider): Response
	{
		$availableStore = $this->storeManager
			->getRepository()
			->findAvailableStoresQB($this->getUser())
			->andWhere('store = :dashboardStore')
			->setParameter('dashboardStore', $store)
			->getQuery()
			->getOneOrNullResult();

		if (!$availableStore instanceof Store) {
			throw $this->createAccessDeniedException();
		}

		return $this->render('admin/store/main.html.twig', [
			'dashboard' => $dashboardProvider->build(
				store: $store,
				from: $this->parseDate($request->query->get('from')),
				to: $this->parseDate($request->query->get('to')),
				warehouseId: $this->parseNullablePositiveInt($request->query->get('warehouse')),
				canViewFinancial: $this->isGranted('dashboard.financial'),
			),
		]);
	}

	private function parseDate(mixed $value): ?DateTimeImmutable
	{
		if (!is_string($value) || $value === '') {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

		return $date ?: null;
	}

	private function parseNullablePositiveInt(mixed $value): ?int
	{
		if (is_int($value)) {
			return $value > 0 ? $value : null;
		}

		if (!is_string($value) || $value === '') {
			return null;
		}

		$integer = filter_var($value, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => 1],
		]);

		return $integer === false ? null : $integer;
	}

}

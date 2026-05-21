<?php

namespace App\Controller\Admin;

use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderMaterial;
use App\Entity\ProductionOrderStatus;
use App\Entity\ProductionRecipe;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Form\Admin\FilterType\ProductionOrderFilterType;
use App\Form\Admin\Type\ProductionOrderCreateType;
use App\Form\Admin\Type\ProductionOrderType;
use App\Manager\ProductionManager;
use App\Repository\StatusHistoryRepository;
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

#[Route('/admin/store/{store_id}/production/order', name: 'app_admin_production_order_'), IsGranted('ROLE_STORE_ADMIN')]
class ProductionOrderController extends AbstractAdvancedController
{
	use QuickActionResponseTrait;

	private const int DEFAULT_PAGE_LIMIT = 20;

	public function __construct(
		private readonly ProductionManager $productionManager,
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
		$queryBuilder = $this->productionManager->getRepository()->findAvailableByStoreQB($store);
		$filterForm = $this->createForm(ProductionOrderFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['productionOrder.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		$order = $request->query->get('id')
			? $this->productionManager->getRepository()->findOneBy([
				'id' => $request->query->get('id'),
				'store' => $store,
			])
			: $pagination->current();

		return $this->render('admin/production_order/index.html.twig', [
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
		$recipe = $this->findRecipeForPrefill($request, $store);
		$form = $this->createForm(ProductionOrderCreateType::class, [
			'recipe' => $recipe,
		], [
			'method' => 'POST',
			'store' => $store,
			'attr' => [
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();

			try {
				$order = $this->productionManager->createOrderFromRecipe(
					$data['recipe'],
					(string) $data['plannedQuantity'],
					$data['warehouse'],
					$data['plannedStartAt'],
					$data['plannedEndAt'],
					$data['comment'],
				);
				$this->productionManager->saveOrder($order);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if (isset($order) && $form->isValid()) {
				return $this->stayOrRedirect(
					route: 'app_admin_production_order_index',
					parameters: ['store_id' => $store->getId()],
					stayRoute: 'app_admin_production_order_edit',
					stayParameters: ['store_id' => $store->getId(), 'id' => $order->getId()],
				);
			}
		}

		return $this->render('admin/production_order/new.html.twig', [
			'form' => $form,
		], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
	}

	private function findRecipeForPrefill(Request $request, Store $store): ?ProductionRecipe
	{
		$recipeId = $request->query->get('recipe_id');

		if (!$recipeId) {
			return null;
		}

		$recipe = $this->productionManager->getRecipeRepository()->findOneBy([
			'id' => $recipeId,
			'store' => $store,
		]);

		if (!$recipe instanceof ProductionRecipe) {
			throw $this->createNotFoundException();
		}

		return $recipe;
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductionOrder $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);
		$this->denyNotEditable($order);

		$originalMaterials = [];
		foreach ($order->getMaterials() as $material) {
			$originalMaterials[$material->getId()] = $material;
		}

		$form = $this->createOrderForm($order, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$removedMaterials = array_filter(
				$originalMaterials,
				static fn (ProductionOrderMaterial $material): bool => !$order->getMaterials()->contains($material),
			);

			try {
				$this->productionManager->saveOrder($order, $removedMaterials);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect('app_admin_production_order_index', ['store_id' => $store->getId()]);
			}
		}

		return $this->renderForm(
			$order,
			$form,
			$this->productionManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
			$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
		);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductionOrder $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		return $this->render('admin/production_order/show.html.twig', [
			'entity' => $order,
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/history', name: 'history', methods: ['GET'])]
	public function history(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductionOrder $order,
	): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		return $this->render('admin/production_order/history.html.twig', [
			'entity' => $order,
			'status_history_entries' => $this->statusHistoryRepository->findTimelineFor($store, StatusHistoryEntityType::PRODUCTION_ORDER, (int) $order->getId()),
		]);
	}

	#[Route('/{id}/plan', name: 'plan', methods: ['POST'])]
	public function plan(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, ProductionOrder $order): Response
	{
		return $this->applyAction($request, $store, $order, 'plan_production_order_', fn () => $this->productionManager->plan($order));
	}

	#[Route('/{id}/reserve-materials', name: 'reserve_materials', methods: ['POST'])]
	public function reserveMaterials(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, ProductionOrder $order): Response
	{
		return $this->applyAction($request, $store, $order, 'reserve_production_order_', fn () => $this->productionManager->reserveMaterials($order));
	}

	#[Route('/{id}/start', name: 'start', methods: ['POST'])]
	public function start(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, ProductionOrder $order): Response
	{
		return $this->applyAction($request, $store, $order, 'start_production_order_', fn () => $this->productionManager->start($order));
	}

	#[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
	public function complete(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, ProductionOrder $order): Response
	{
		return $this->applyAction($request, $store, $order, 'complete_production_order_', function () use ($request, $order): void {
			$this->productionManager->complete($order, (string) $request->request->get('completedQuantity', $order->getPlannedQuantity()));
		});
	}

	#[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
	public function cancel(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, ProductionOrder $order): Response
	{
		return $this->applyAction($request, $store, $order, 'cancel_production_order_', fn () => $this->productionManager->cancel($order));
	}

	private function applyAction(Request $request, Store $store, ProductionOrder $order, string $csrfPrefix, callable $action): Response
	{
		$this->denyOrderOutsideStore($order, $store);

		if (!$this->isCsrfTokenValid($csrfPrefix . $order->getId(), (string) $request->request->get('_token'))) {
			$this->addFlash('danger', 'Production action token is invalid.');

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		try {
			$action();
			$this->addFlash('success', 'Production order action completed.');
		} catch (RuntimeException $exception) {
			$this->addFlash('danger', $exception->getMessage());

			return $this->quickActionResponse($request, $store, $order, Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		return $this->quickActionResponse($request, $store, $order);
	}

	private function createOrderForm(ProductionOrder $order, Store $store): FormInterface
	{
		return $this->createForm(ProductionOrderType::class, $order, [
			'method' => 'POST',
			'store' => $store,
			'attr' => [
				'data-select-two-target' => 'form',
			],
		]);
	}

	private function renderForm(ProductionOrder $order, FormInterface $form, ?int $orderIndexPage, int $status): Response
	{
		$response = $this->render('admin/production_order/form.html.twig', [
			'entity' => $order,
			'form' => $form,
			'order_index_page' => $orderIndexPage,
		]);
		$response->setStatusCode($status);

		return $response;
	}

	private function denyOrderOutsideStore(ProductionOrder $order, Store $store): void
	{
		if ($order->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}

	private function denyNotEditable(ProductionOrder $order): void
	{
		if (!in_array($order->getStatus(), [ProductionOrderStatus::DRAFT, ProductionOrderStatus::PLANNED], true)) {
			throw $this->createAccessDeniedException('Only draft or planned production orders can be changed.');
		}
	}

	private function redirectToOrderIndex(Store $store, ProductionOrder $order): Response
	{
		return $this->redirectToRoute('app_admin_production_order_index', [
			'store_id' => $store->getId(),
			'id' => $order->getId(),
			'page' => $this->productionManager->getRepository()->getIndexPage($order, self::DEFAULT_PAGE_LIMIT),
		]);
	}

	private function quickActionResponse(Request $request, Store $store, ProductionOrder $order, int $status = Response::HTTP_OK): Response
	{
		if (!$this->wantsQuickActionJson($request)) {
			return $this->redirectToOrderIndex($store, $order);
		}

		return $this->quickActionJsonResponse($request, [
			'card' => $this->renderView('admin/production_order/show.html.twig', [
				'entity' => $order,
				'query_params' => $request->query->all(),
			]),
			'history' => $this->renderView('admin/production_order/history.html.twig', [
				'entity' => $order,
				'status_history_entries' => $this->statusHistoryRepository->findTimelineFor($store, StatusHistoryEntityType::PRODUCTION_ORDER, (int) $order->getId()),
			]),
			'row' => $this->renderView('admin/production_order/_index_row.html.twig', [
				'entity' => $order,
				'first_entity' => $order,
			]),
		], $status);
	}
}

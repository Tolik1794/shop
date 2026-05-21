<?php

namespace App\Controller\Admin;

use App\Entity\ProductionRecipe;
use App\Entity\ProductionRecipeItem;
use App\Entity\Store;
use App\Form\Admin\Type\ProductionRecipeType;
use App\Manager\ProductionManager;
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

#[Route('/admin/store/{store_id}/production/recipe', name: 'app_admin_production_recipe_'), IsGranted('ROLE_STORE_ADMIN')]
class ProductionRecipeController extends AbstractAdvancedController
{
	private const int DEFAULT_PAGE_LIMIT = 20;

	public function __construct(private readonly ProductionManager $productionManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$queryBuilder = $this->productionManager->getRecipeRepository()->findAvailableByStoreQB($store);
		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['productionRecipe.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		$recipe = $request->query->get('id')
			? $this->productionManager->getRecipeRepository()->findOneBy([
				'id' => $request->query->get('id'),
				'store' => $store,
			])
			: $pagination->current();

		return $this->render('admin/production_recipe/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $recipe,
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$recipe = (new ProductionRecipe())
			->setStore($store)
			->addItem(new ProductionRecipeItem());
		$form = $this->createRecipeForm($recipe, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$this->productionManager->saveRecipe($recipe);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect(
					route: 'app_admin_production_recipe_index',
					parameters: ['store_id' => $store->getId()],
					stayRoute: 'app_admin_production_recipe_edit',
					stayParameters: ['store_id' => $store->getId(), 'id' => $recipe->getId()],
				);
			}
		}

		return $this->renderForm($recipe, $form, null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductionRecipe $recipe,
	): Response
	{
		$this->denyRecipeOutsideStore($recipe, $store);

		$originalItems = [];
		foreach ($recipe->getItems() as $item) {
			$originalItems[$item->getId()] = $item;
		}

		$form = $this->createRecipeForm($recipe, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$removedItems = array_filter(
				$originalItems,
				static fn (ProductionRecipeItem $item): bool => !$recipe->getItems()->contains($item),
			);

			try {
				$this->productionManager->saveRecipe($recipe, $removedItems);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect('app_admin_production_recipe_index', ['store_id' => $store->getId()]);
			}
		}

		return $this->renderForm(
			$recipe,
			$form,
			$this->productionManager->getRecipeRepository()->getIndexPage($recipe, self::DEFAULT_PAGE_LIMIT),
			$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
		);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductionRecipe $recipe,
	): Response
	{
		$this->denyRecipeOutsideStore($recipe, $store);

		return $this->render('admin/production_recipe/show.html.twig', [
			'entity' => $recipe,
			'query_params' => $request->query->all(),
		]);
	}

	private function createRecipeForm(ProductionRecipe $recipe, Store $store): FormInterface
	{
		return $this->createForm(ProductionRecipeType::class, $recipe, [
			'method' => 'POST',
			'store' => $store,
			'material_ajax_url' => $this->generateUrl('app_api_admin_production_search_material_products', [
				'store_id' => $store->getId(),
			]),
			'attr' => [
				'data-select-two-target' => 'form',
			],
		]);
	}

	private function renderForm(ProductionRecipe $recipe, FormInterface $form, ?int $recipeIndexPage, int $status): Response
	{
		$response = $this->render('admin/production_recipe/form.html.twig', [
			'entity' => $recipe,
			'form' => $form,
			'recipe_index_page' => $recipeIndexPage,
		]);
		$response->setStatusCode($status);

		return $response;
	}

	private function denyRecipeOutsideStore(ProductionRecipe $recipe, Store $store): void
	{
		if ($recipe->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}
}

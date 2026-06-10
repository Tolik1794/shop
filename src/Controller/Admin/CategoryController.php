<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Store;
use App\Form\Admin\FilterType\CategoryFilterType;
use App\Form\Admin\Type\CategoryType;
use App\Manager\CategoryManager;
use App\Repository\CategoryProductParameterNameRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductParameterNameRepository;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/category', name: 'admin_category_'), IsGranted('category.manage')]
class CategoryController extends AbstractAdvancedController
{
	public function __construct(private readonly CategoryManager $categoryManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		Request $request,
		PaginatorInterface $paginator,
		FilterFormHandler $filterTypeHandler,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store
	): Response
	{
		$queryBuilder = $this->categoryManager
			->getRepository()
			->findAvailableCategoriesQB($store);

		$filterForm = $this->createForm(CategoryFilterType::class);
		$filterForm->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$category = $this->categoryManager->getRepository()->find($id);
		} else {
			$category = $pagination->current();
		}

		return $this->render('admin/category/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $category,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[IsGranted('category.manage')]
	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		CategoryProductParameterNameRepository $categoryParameterRepository,
		#[MapEntity(expr: 'repository.find(store_id)')] Store $store,
	): Response
	{
		$category = new Category();
		$category->setStore($store);
		$canManageParameters = $this->isGranted('category_parameter.manage');
		$form = $this->createForm(CategoryType::class, $category, [
			'method' => 'POST',
			'manage_parameters' => $canManageParameters,
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->categoryManager->save($category);

			return $this->stayOrRedirect(
				route: 'admin_category_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'admin_category_edit',
				stayParameters: ['store_id' => $store->getId(), 'category_id' => $category->getId()],
			);
		}

		return $this->render('admin/category/form.html.twig', [
			'entity' => $category,
			'form' => $form,
			'inherited_parameters' => $this->buildInheritedParameters($category->getParent(), $categoryParameterRepository),
		]);
	}

	#[Route('/{category_id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(category_id)')]
		Category $category,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		CategoryProductParameterNameRepository $categoryParameterRepository,
	): Response
	{
		$canManageParameters = $this->isGranted('category_parameter.manage');
		$form = $this->createForm(CategoryType::class, $category, [
			'method' => 'POST',
			'manage_parameters' => $canManageParameters,
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->categoryManager->save($category);

			return $this->stayOrRedirect('admin_category_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/category/form.html.twig', [
			'entity' => $category,
			'form' => $form,
			'inherited_parameters' => $this->buildInheritedParameters($category->getParent(), $categoryParameterRepository),
		]);
	}

	#[Route('/parameter-options', name: 'parameter_options', methods: ['GET'])]
	public function parameterOptions(
		Request $request,
		CategoryRepository $categoryRepository,
		CategoryProductParameterNameRepository $categoryParameterRepository,
		ProductParameterNameRepository $parameterNameRepository,
		#[MapEntity(expr: 'repository.find(store_id)')] Store $store,
	): JsonResponse
	{
		$parent = null;
		$parentId = $request->query->getInt('parent_id');
		if ($parentId > 0) {
			$parent = $categoryRepository->find($parentId);
			if (!$parent || $parent->getStore()?->getId() !== $store->getId()) {
				return $this->json(['available' => [], 'inherited' => []], Response::HTTP_NOT_FOUND);
			}
		}

		$inherited = $this->buildInheritedParameters($parent, $categoryParameterRepository);
		$available = [];

		if ($this->isGranted('category_parameter.manage')) {
			$inheritedIds = array_fill_keys(array_column($inherited, 'id'), true);
			foreach ($parameterNameRepository->findBy([], ['name' => 'ASC']) as $parameterName) {
				if ($parameterName->getId() && !isset($inheritedIds[$parameterName->getId()])) {
					$available[] = [
						'id' => $parameterName->getId(),
						'name' => $parameterName->getName(),
					];
				}
			}
		}

		return $this->json([
			'available' => $available,
			'inherited' => $inherited,
		]);
	}

	#[Route('/{category_id}/show', name: 'show')]
	public function show(Request $request, #[MapEntity(expr: 'repository.find(category_id)')] Category $category): Response
	{
		return $this->render('admin/category/show.html.twig', [
			'entity' => $category,
			'query_params' => $request->query->all()
		]);
	}

	private function buildInheritedParameters(
		?Category $parent,
		CategoryProductParameterNameRepository $categoryParameterRepository,
	): array {
		$parameters = [];
		foreach ($categoryParameterRepository->findInheritedByParent($parent) as $categoryParameter) {
			$parameterName = $categoryParameter->getProductParameterName();
			$sourceCategory = $categoryParameter->getCategory();
			if (!$parameterName?->getId() || isset($parameters[$parameterName->getId()])) {
				continue;
			}

			$parameters[$parameterName->getId()] = [
				'id' => $parameterName->getId(),
				'name' => $parameterName->getName(),
				'isRequired' => (bool) $categoryParameter->isIsRequired(),
				'isFilter' => (bool) $categoryParameter->isIsFilter(),
				'sourceCategory' => $sourceCategory?->getNameWithParent(),
			];
		}

		uasort($parameters, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

		return array_values($parameters);
	}
}

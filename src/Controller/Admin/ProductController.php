<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Store;
use App\Form\Admin\FilterType\ProductFilterType;
use App\Form\Admin\Type\ProductType;
use App\Manager\ProductManager;
use App\Manager\ProductRelationManager;
use App\Repository\CategoryProductParameterNameRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductPriceRepository;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/product', name: 'admin_product_'), IsGranted('product.view')]
class ProductController extends AbstractAdvancedController
{
	private const DEFAULT_PAGE_LIMIT = 20;

	public function __construct(
		private readonly ProductManager $productManager,
		private readonly ProductRelationManager $productRelationManager,
		private readonly ProductPriceRepository $productPriceRepository,
		private readonly CategoryRepository $categoryRepository,
		private readonly CategoryProductParameterNameRepository $categoryProductParameterNameRepository,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request            $request,
		FilterFormHandler  $filterTypeHandler,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store              $store,
	): Response
	{
		$queryBuilder = $this->productManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(ProductFilterType::class, null, [
			'store' => $store,
		])->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['product.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$product = $this->productManager->getRepository()->find($id);
		} else {
			$product = $pagination->current();
		}

		return $this->render('admin/product/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $product,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[IsGranted('product.manage')]
	#[Route('/category-parameters', name: 'category_parameters', methods: ['GET'])]
	public function categoryParameters(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): JsonResponse
	{
		$categoryId = $request->query->getInt('category_id');
		if ($categoryId < 1) {
			return $this->json(['parameters' => []]);
		}

		$category = $this->categoryRepository->find($categoryId);
		if (!$category || $category->getStore()?->getId() !== $store->getId()) {
			return $this->json(['parameters' => []], Response::HTTP_NOT_FOUND);
		}

		$parameters = [];
		foreach ($this->categoryProductParameterNameRepository->findAllByCategory($category) as $categoryProductParameterName) {
			$productParameterName = $categoryProductParameterName->getProductParameterName();
			if (!$productParameterName || !$productParameterName->getId()) {
				continue;
			}

			$parameters[$productParameterName->getId()] = [
				'id' => $productParameterName->getId(),
				'name' => $productParameterName->getName(),
				'isRequired' => (bool) $categoryProductParameterName->isIsRequired(),
			];
		}

		usort($parameters, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

		return $this->json(['parameters' => $parameters]);
	}

	#[IsGranted('product.manage')]
	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$product = new Product();
		$product->setStore($store)
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);
		$form = $this->createForm(ProductType::class, $product, [
			'method' => 'POST',
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->productManager->save($product);
			$this->productRelationManager->syncMirrors($product);

			return $this->stayOrRedirect(
				route: 'admin_product_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'admin_product_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $product->getId()],
			);
		}

		return $this->render('admin/product/form.html.twig', [
			'entity' => $product,
			'form' => $form
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('product.manage')]
	public function edit(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store, Product $product): Response
	{
//		$this->denyAccessUnlessGranted(StoreVoter::EDIT, $store);
		$form = $this->createForm(ProductType::class, $product, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->productManager->save($product);
			$this->productRelationManager->syncMirrors($product);

			return $this->stayOrRedirect('admin_product_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/product/form.html.twig', [
			'entity' => $product,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(Request $request, Product $product): Response
	{
		return $this->render('admin/product/show.html.twig', [
			'entity' => $product,
			'current_price' => $this->productPriceRepository->findCurrentRegularPrice($product, $product->getStore()?->getBaseCurrency()),
			'index_page' => $this->productManager->getRepository()->getIndexPage($product, self::DEFAULT_PAGE_LIMIT),
			'query_params' => $request->query->all()
		]);
	}
}

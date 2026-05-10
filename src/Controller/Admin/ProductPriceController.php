<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Form\Admin\Type\ProductPriceType;
use App\Manager\ProductPriceManager;
use App\Repository\ProductRepository;
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

#[Route('/admin/store/{store_id}/product/price', name: 'app_admin_product_price_'), IsGranted('ROLE_STORE_ADMIN')]
class ProductPriceController extends AbstractAdvancedController
{
	private const DEFAULT_PAGE_LIMIT = 20;

	public function __construct(
		private readonly ProductPriceManager $productPriceManager,
		private readonly ProductRepository $productRepository,
	)
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
		$product = $this->resolveProduct($request, $store);
		$queryBuilder = $this->productPriceManager
			->getRepository()
			->findAvailableByProductQB($product);

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['productPrice.validFrom', 'productPrice.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$productPrice = $this->productPriceManager->getRepository()->findOneBy([
				'id' => $id,
				'product' => $product,
			]);
		} else {
			$productPrice = $pagination->current();
		}

		return $this->render('admin/product_price/index.html.twig', [
			'product' => $product,
			'product_index_page' => $this->productRepository->getIndexPage($product, self::DEFAULT_PAGE_LIMIT),
			'pagination' => $pagination,
			'first_entity' => $productPrice,
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$product = $this->resolveProduct($request, $store);
		$productPrice = (new ProductPrice())
			->setStore($store)
			->setProduct($product)
			->setCurrency($store->getBaseCurrency());

		$form = $this->createForm(ProductPriceType::class, $productPrice, [
			'method' => 'POST',
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->isValidProductPrice($productPrice, $form) && $form->isValid()) {
			try {
				$this->productPriceManager->saveWithTimeline($productPrice);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect(
					route: 'app_admin_product_price_index',
					parameters: ['store_id' => $store->getId(), 'product_id' => $product->getId()],
					stayRoute: 'app_admin_product_price_edit',
					stayParameters: ['store_id' => $store->getId(), 'product_id' => $product->getId(), 'id' => $productPrice->getId()],
				);
			}
		}

		return $this->render('admin/product_price/form.html.twig', [
			'product' => $product,
			'product_index_page' => $this->productRepository->getIndexPage($product, self::DEFAULT_PAGE_LIMIT),
			'product_price_index_page' => $productPrice->getId() ? $this->productPriceManager->getRepository()->getIndexPage($productPrice, self::DEFAULT_PAGE_LIMIT) : null,
			'entity' => $productPrice,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductPrice $productPrice,
	): Response
	{
		$product = $productPrice->getProduct();

		if (!$product instanceof Product || $product->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		$form = $this->createForm(ProductPriceType::class, $productPrice, [
			'method' => 'POST',
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->isValidProductPrice($productPrice, $form) && $form->isValid()) {
			try {
				$this->productPriceManager->saveWithTimeline($productPrice);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect('app_admin_product_price_index', [
					'store_id' => $store->getId(),
					'product_id' => $product->getId(),
				]);
			}
		}

		return $this->render('admin/product_price/form.html.twig', [
			'product' => $product,
			'product_index_page' => $this->productRepository->getIndexPage($product, self::DEFAULT_PAGE_LIMIT),
			'product_price_index_page' => $this->productPriceManager->getRepository()->getIndexPage($productPrice, self::DEFAULT_PAGE_LIMIT),
			'entity' => $productPrice,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductPrice $productPrice,
	): Response
	{
		if ($productPrice->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		return $this->render('admin/product_price/show.html.twig', [
			'entity' => $productPrice,
		]);
	}

	private function resolveProduct(Request $request, Store $store): Product
	{
		$productId = $request->query->getInt('product_id');
		$product = $productId
			? $this->productRepository->findOneBy(['id' => $productId, 'store' => $store])
			: null;

		if (!$product instanceof Product) {
			throw $this->createNotFoundException();
		}

		return $product;
	}

	private function isValidProductPrice(ProductPrice $productPrice, FormInterface $form): bool
	{
		if ((float)$productPrice->getPrice() <= 0) {
			$form->get('price')->addError(new FormError('Price must be greater than zero.'));
		}

		if ($productPrice->getProduct()?->getStore()?->getId() !== $productPrice->getStore()?->getId()) {
			$form->addError(new FormError('Product does not belong to selected store.'));
		}

		if ($productPrice->getId() === null && $this->productPriceManager->getRepository()->hasBlockingPriceForNewStart($productPrice)) {
			$form->addError(new FormError('New product price cannot be created inside an existing price interval or before a later price.'));
		}

		if ($productPrice->getId() !== null && $this->productPriceManager->getRepository()->hasOverlappingPrice($productPrice)) {
			$form->addError(new FormError('Product price interval overlaps another price for the same product, type and currency.'));
		}

		return $form->isValid();
	}
}

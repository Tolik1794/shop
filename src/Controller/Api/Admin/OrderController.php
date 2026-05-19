<?php

namespace App\Controller\Api\Admin;

use App\Dto\Api\Admin\Order\ProductSearchProductDto;
use App\Dto\Api\Admin\Order\ProductSearchResponseDto;
use App\Entity\Currency;
use App\Entity\Store;
use App\Repository\CurrencyRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Service\OrderCalculator;
use App\Service\Pricing\CatalogPriceResolver;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/store/{store_id}/order', name: 'app_api_admin_order_'), IsGranted('ROLE_STORE_ADMIN')]
class OrderController extends AbstractController
{
	public function __construct(
		private readonly ProductRepository $productRepository,
		private readonly CurrencyRepository $currencyRepository,
		private readonly CustomerRepository $customerRepository,
		private readonly OrderCalculator $orderCalculator,
		private readonly CatalogPriceResolver $catalogPriceResolver,
	)
	{
	}

	#[Route('/product-search', name: 'search_products_for_order_entry', methods: ['GET'])]
	public function searchProductsForOrderEntry(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): JsonResponse
	{
		$search = trim((string) $request->query->get('q', ''));
		$page = max(1, $request->query->getInt('page', 1));
		$limit = min(50, max(1, $request->query->getInt('limit', 20)));
		$excludedOptionKeys = array_filter(
			$request->query->all('excludedOptions'),
			static fn(mixed $value): bool => is_string($value) && $value !== '',
		);
		$currency = $this->resolveSearchCurrency($request, $store);

		if (mb_strlen($search) < 3) {
			return $this->json(new ProductSearchResponseDto([], $page, false));
		}

		$filteredProducts = $this->findProductsForOrderEntrySearch($store, $currency, $search, $excludedOptionKeys, $page, $limit);

		return $this->json(new ProductSearchResponseDto(
			products: $filteredProducts['products'],
			page: $page,
			hasMore: $filteredProducts['hasMore'],
		));
	}

	#[Route('/customer-search', name: 'customer_search', methods: ['GET'])]
	public function customerSearch(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): JsonResponse
	{
		$phone = trim((string) $request->query->get('phone', ''));

		if (mb_strlen($phone) < 5) {
			return $this->json(['customers' => []]);
		}

		$customers = array_map(static fn($customer): array => [
			'id' => $customer->getId(),
			'name' => $customer->getName(),
			'lastName' => $customer->getLastName(),
			'fullName' => $customer->getFullName(),
			'phone' => $customer->getPhone(),
		], $this->customerRepository->findAvailableByStoreAndPhoneSearch($store, $phone));

		return $this->json(['customers' => $customers]);
	}

	#[Route('/summary', name: 'summary', methods: ['POST'])]
	public function summary(Request $request): JsonResponse
	{
		$payload = $request->request->all('order');

		return $this->json($this->orderCalculator->calculatePayload($payload));
	}

	/**
	 * @param string[] $excludedOptionKeys
	 * @return array{products: ProductSearchProductDto[], hasMore: bool}
	 */
	private function findProductsForOrderEntrySearch(Store $store, Currency $currency, string $search, array $excludedOptionKeys, int $page, int $limit): array
	{
		$chunkSize = 50;
		$offset = 0;
		$neededCount = ($page * $limit) + 1;
		$products = [];

		do {
			$foundProducts = $this->productRepository->findChoicesByStoreAndSearch($store, $search, $chunkSize, $offset);

			foreach ($foundProducts as $product) {
				$productDto = ProductSearchProductDto::fromProduct(
					$product,
					$store,
					$this->catalogPriceResolver->tryResolve($product, $store, $currency)?->getAmount(),
					$excludedOptionKeys,
				);

				if ($productDto instanceof ProductSearchProductDto) {
					$products[] = $productDto;
				}

				if (count($products) >= $neededCount) {
					break 2;
				}
			}

			$offset += $chunkSize;
		} while (count($foundProducts) === $chunkSize);

		$pageOffset = ($page - 1) * $limit;

		return [
			'products' => array_slice($products, $pageOffset, $limit),
			'hasMore' => count($products) > $pageOffset + $limit,
		];
	}

	private function resolveSearchCurrency(Request $request, Store $store): Currency
	{
		$currencyCode = trim((string) $request->query->get('currency', ''));
		$currency = $currencyCode !== '' ? $this->currencyRepository->find($currencyCode) : null;

		if ($currency instanceof Currency) {
			return $currency;
		}

		$baseCurrency = $store->getBaseCurrency();

		if (!$baseCurrency instanceof Currency) {
			throw new RuntimeException('Store has no base currency.');
		}

		return $baseCurrency;
	}
}

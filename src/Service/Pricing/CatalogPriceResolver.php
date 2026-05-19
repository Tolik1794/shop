<?php

namespace App\Service\Pricing;

use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Repository\ProductPriceRepository;
use App\Service\ExchangeRateResolver;
use DateTimeImmutable;
use RuntimeException;

/**
 * Resolves the shared default catalog price used by order entry creation and product search.
 */
class CatalogPriceResolver
{
	public function __construct(
		private readonly ProductPriceRepository $productPriceRepository,
		private readonly ExchangeRateResolver $exchangeRateResolver,
	)
	{
	}

	/**
	 * Resolves the default catalog price required for a business operation.
	 *
	 * @throws RuntimeException when neither a current regular price nor a usable base fallback exists
	 */
	public function resolve(
		Product $product,
		Store $store,
		Currency $currency,
		?DateTimeImmutable $date = null,
	): ResolvedPrice {
		$productPrice = $this->productPriceRepository->findCurrentRegularPrice($product, $currency, $date);

		if ($productPrice instanceof ProductPrice && $productPrice->getPrice() !== null) {
			return new ResolvedPrice(
				amount: $this->formatMoney((float) $productPrice->getPrice()),
				currency: $currency,
				source: ResolvedPrice::SOURCE_PRODUCT_PRICE,
			);
		}

		$baseSalePrice = $product->getBaseSalePrice();
		$baseCurrency = $store->getBaseCurrency();

		if ($baseSalePrice === null || !$baseCurrency instanceof Currency) {
			throw new RuntimeException(sprintf('Product "%s" has no sale price.', $product->getName()));
		}

		$exchangeRateToBase = (float) $this->exchangeRateResolver->resolve($currency, $baseCurrency, $store, $date);
		$amount = $exchangeRateToBase > 0
			? (float) $baseSalePrice / $exchangeRateToBase
			: (float) $baseSalePrice;

		return new ResolvedPrice(
			amount: $this->formatMoney($amount),
			currency: $currency,
			source: ResolvedPrice::SOURCE_BASE_SALE_PRICE,
		);
	}

	/**
	 * Attempts to resolve a catalog price without failing optional UI flows.
	 */
	public function tryResolve(
		Product $product,
		Store $store,
		Currency $currency,
		?DateTimeImmutable $date = null,
	): ?ResolvedPrice {
		try {
			return $this->resolve($product, $store, $currency, $date);
		} catch (RuntimeException) {
			return null;
		}
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

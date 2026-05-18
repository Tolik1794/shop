<?php

namespace App\Service\Pricing;

use App\Entity\Currency;

/**
 * Immutable result of catalog price resolution.
 */
class ResolvedPrice
{
	/**
	 * Price came from the dated ProductPrice timeline in the requested currency.
	 */
	public const SOURCE_PRODUCT_PRICE = 'product_price';

	/**
	 * Price came from Product::baseSalePrice and may have been currency-converted.
	 */
	public const SOURCE_BASE_SALE_PRICE = 'base_sale_price';

	public function __construct(
		private readonly string $amount,
		private readonly Currency $currency,
		private readonly string $source,
	)
	{
	}

	public function getAmount(): string
	{
		return $this->amount;
	}

	public function getCurrency(): Currency
	{
		return $this->currency;
	}

	public function getSource(): string
	{
		return $this->source;
	}
}

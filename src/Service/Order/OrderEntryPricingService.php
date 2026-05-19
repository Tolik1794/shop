<?php

namespace App\Service\Order;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\Store;
use App\Service\Pricing\CatalogPriceResolver;

/**
 * Initializes default prices for order entries without repricing existing values.
 */
class OrderEntryPricingService
{
	public function __construct(private readonly CatalogPriceResolver $catalogPriceResolver)
	{
	}

	/**
	 * Sets an initial catalog price only when the entry does not already carry a manual or snapshotted price.
	 */
	public function initializeUnitPrice(OrderEntry $orderEntry, Order $order): void
	{
		if ((float) $orderEntry->getUnitPrice() > 0) {
			return;
		}

		$product = $orderEntry->getProduct();
		$store = $order->getStore();
		$currency = $order->getCurrency();

		if (!$product instanceof Product || !$store instanceof Store || $currency === null) {
			return;
		}

		$orderEntry->setUnitPrice(
			$this->catalogPriceResolver->resolve($product, $store, $currency)->getAmount()
		);
	}
}

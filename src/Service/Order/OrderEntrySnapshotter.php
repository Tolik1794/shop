<?php

namespace App\Service\Order;

use App\Entity\OrderEntry;
use App\Entity\Product;

/**
 * Copies product display data into the immutable order entry snapshot fields.
 */
class OrderEntrySnapshotter
{
	/**
	 * Copies the current product display data into the order entry snapshot fields.
	 */
	public function snapshot(OrderEntry $orderEntry): void
	{
		$product = $orderEntry->getProduct();

		if (!$product instanceof Product) {
			return;
		}

		$orderEntry
			->setProductNameSnapshot((string) $product->getName())
			->setProductCodeSnapshot((string) $product->getCode())
			->setUnitCodeSnapshot((string) $product->getUnit()?->getCode())
			->setUnitNameSnapshot((string) $product->getUnit()?->getName());
	}
}

<?php

namespace App\Service\Purchase;

use App\Entity\Product;
use App\Entity\PurchaseEntry;

/**
 * Copies product display data into the immutable purchase entry snapshot fields.
 */
class PurchaseEntrySnapshotter
{
	public function snapshot(PurchaseEntry $purchaseEntry): void
	{
		$product = $purchaseEntry->getProduct();

		if (!$product instanceof Product) {
			return;
		}

		$purchaseEntry
			->setProductNameSnapshot((string) $product->getName())
			->setProductCodeSnapshot((string) $product->getCode())
			->setUnitCodeSnapshot((string) $product->getUnit()?->getCode())
			->setUnitNameSnapshot((string) $product->getUnit()?->getName());
	}
}

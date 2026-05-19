<?php

namespace App\Validator\Constraints;

use App\Entity\Product;
use App\Entity\PurchaseEntry;
use App\Entity\Warehouse;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PurchaseEntryForStoreValidator extends ConstraintValidator
{
	public function validate(mixed $value, Constraint $constraint): void
	{
		if (!$constraint instanceof PurchaseEntryForStore) {
			throw new UnexpectedTypeException($constraint, PurchaseEntryForStore::class);
		}

		if (!$value instanceof PurchaseEntry) {
			return;
		}

		$product = $value->getProduct();
		$warehouse = $value->getWarehouse();
		$store = $constraint->store;
		$isProductValid = $product instanceof Product && $product->getStore()?->getId() === $store->getId();
		$isWarehouseValid = $warehouse instanceof Warehouse && $warehouse->getStore()?->getId() === $store->getId();

		if (!$isProductValid) {
			$this->context
				->buildViolation($constraint->invalidProductMessage)
				->atPath('product')
				->addViolation();
		}

		if ($isProductValid && !$product->isCanBePurchased()) {
			$this->context
				->buildViolation($constraint->notPurchasableProductMessage)
				->atPath('product')
				->addViolation();
		}

		if (!$isWarehouseValid) {
			$this->context
				->buildViolation($constraint->invalidWarehouseMessage)
				->atPath('warehouse')
				->addViolation();
		}
	}
}

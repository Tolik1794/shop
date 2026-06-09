<?php

namespace App\Validator\Constraints;

use App\Entity\OrderEntry;
use App\Entity\OrderEntryFulfillmentSource;
use App\Entity\Product;
use App\Enum\ProductKindEnum;
use App\Repository\WarehouseStockRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class OrderEntryForStoreValidator extends ConstraintValidator
{
	public function __construct(private readonly WarehouseStockRepository $warehouseStockRepository)
	{
	}

	public function validate(mixed $value, Constraint $constraint): void
	{
		if (!$constraint instanceof OrderEntryForStore) {
			throw new UnexpectedTypeException($constraint, OrderEntryForStore::class);
		}

		if (!$value instanceof OrderEntry) {
			return;
		}

		$product = $value->getProduct();
		$warehouse = $value->getWarehouse();
		$store = $constraint->store;
		$isProductValid = $product instanceof Product && $product->getStore()?->getId() === $store->getId();
		$isWarehouseValid = $warehouse === null || $warehouse->getStore()?->getId() === $store->getId();

		if (!$isProductValid) {
			$this->context
				->buildViolation($constraint->invalidProductMessage)
				->atPath('product')
				->addViolation();
		}

		if (!$isWarehouseValid) {
			$this->context
				->buildViolation($constraint->invalidWarehouseMessage)
				->atPath('warehouse')
				->addViolation();
		}

		if (
			$warehouse === null
			&& $product?->getProductKind() !== ProductKindEnum::SERVICE
			&& $value->getFulfillmentSource() === OrderEntryFulfillmentSource::STOCK
		) {
			$this->context
				->buildViolation($constraint->warehouseRequiredMessage)
				->atPath('warehouse')
				->addViolation();
		}

		if (
			$isProductValid
			&& (
				($product->getProductKind() === ProductKindEnum::SERVICE && $value->getFulfillmentSource() !== OrderEntryFulfillmentSource::SERVICE)
				|| ($product->getProductKind() !== ProductKindEnum::SERVICE && $value->getFulfillmentSource() === OrderEntryFulfillmentSource::SERVICE)
			)
		) {
			$this->context
				->buildViolation($constraint->invalidFulfillmentSourceMessage)
				->atPath('fulfillmentSource')
				->addViolation();
		}

		if (
			!$isProductValid
			|| !$isWarehouseValid
			|| $warehouse === null
			|| $value->getFulfillmentSource() !== OrderEntryFulfillmentSource::STOCK
		) {
			return;
		}

		if ($store->isAllowBackorders()) {
			return;
		}

		$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);
		$availableQuantity = $warehouseStock !== null
			? max(0, (float) $warehouseStock->getQuantityOnHand() - (float) $warehouseStock->getReservedQuantity())
			: 0;

		if ((float) $value->getQuantity() > $availableQuantity) {
			$this->context
				->buildViolation($constraint->insufficientStockMessage)
				->setParameter('{{ available }}', number_format($availableQuantity, 4, '.', ''))
				->atPath('quantity')
				->addViolation();
		}
	}
}

<?php

namespace App\Validator;

use App\Entity\WarehouseStock;
use App\Repository\WarehouseStockRepository;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class WarehouseStockBusinessValidator
{
	public function __construct(private readonly WarehouseStockRepository $warehouseStockRepository)
	{
	}

	public function validate(WarehouseStock $warehouseStock, FormInterface $form): bool
	{
		if ($this->warehouseStockRepository->existsForProductAndWarehouse($warehouseStock)) {
			$form->addError(new FormError('Stock row for selected warehouse and product already exists.'));
		}

		if (!$warehouseStock->getWarehouse()?->getStore()?->isAllowBackorders()
			&& (float) $warehouseStock->getReservedQuantity() > (float) $warehouseStock->getQuantityOnHand()
		) {
			$form->get('reservedQuantity')->addError(new FormError('Reserved quantity cannot be greater than quantity on hand.'));
		}

		return $form->isValid();
	}
}

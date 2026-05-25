<?php

namespace App\Dto\Admin\Inventory;

use App\Entity\InventoryReason;
use App\Entity\PurchaseEntry;

class InventorySupplierReturnOperation
{
	public ?PurchaseEntry $purchaseEntry = null;
	public ?InventoryReason $reason = null;
	public string|float|int|null $quantity = null;
	public ?string $number = null;
}

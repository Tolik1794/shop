<?php

namespace App\Dto\Admin\Inventory;

use App\Entity\InventoryReason;
use App\Entity\Product;
use App\Entity\Warehouse;

class InventoryWriteOffOperation
{
	public ?Product $product = null;
	public ?Warehouse $warehouse = null;
	public ?InventoryReason $reason = null;
	public string|float|int|null $quantity = null;
	public ?string $number = null;
}

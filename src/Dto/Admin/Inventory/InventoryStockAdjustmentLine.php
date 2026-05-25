<?php

namespace App\Dto\Admin\Inventory;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Enum\InventoryDirection;

class InventoryStockAdjustmentLine
{
	public ?Product $product = null;
	public ?Warehouse $warehouse = null;
	public InventoryDirection $direction = InventoryDirection::IN;
	public string|float|int|null $quantity = null;
	public string|float|int|null $unitCost = null;
}

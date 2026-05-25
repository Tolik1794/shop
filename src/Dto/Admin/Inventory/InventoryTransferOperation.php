<?php

namespace App\Dto\Admin\Inventory;

use App\Entity\InventoryReason;
use App\Entity\Product;
use App\Entity\Warehouse;

class InventoryTransferOperation
{
	public ?Product $product = null;
	public ?Warehouse $sourceWarehouse = null;
	public ?Warehouse $destinationWarehouse = null;
	public ?InventoryReason $reason = null;
	public string|float|int|null $quantity = null;
	public ?string $number = null;
}

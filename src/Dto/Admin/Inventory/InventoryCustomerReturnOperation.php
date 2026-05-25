<?php

namespace App\Dto\Admin\Inventory;

use App\Entity\InventoryReason;
use App\Entity\OrderEntry;

class InventoryCustomerReturnOperation
{
	public ?OrderEntry $orderEntry = null;
	public ?InventoryReason $reason = null;
	public string|float|int|null $quantity = null;
	public ?string $number = null;
}

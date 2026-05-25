<?php

namespace App\Dto\Admin\Inventory;

use App\Entity\InventoryReason;

class InventoryStockAdjustmentOperation
{
	public ?InventoryReason $reason = null;
	public ?string $number = null;

	/**
	 * @var list<InventoryStockAdjustmentLine>
	 */
	public array $lines;

	public function __construct()
	{
		$this->lines = [new InventoryStockAdjustmentLine()];
	}
}

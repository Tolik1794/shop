<?php

namespace App\Dto\Api\Admin\Order;

use JsonSerializable;

class ProductSearchStockOptionDto implements JsonSerializable
{
	public function __construct(
		private readonly ?int $warehouseId,
		private readonly ?string $warehouseName,
		private readonly string $available,
		private readonly ?string $price,
	)
	{
	}

	public function jsonSerialize(): array
	{
		return [
			'type' => 'stock',
			'warehouseId' => $this->warehouseId,
			'warehouseName' => $this->warehouseName,
			'available' => $this->available,
			'price' => $this->price,
		];
	}
}

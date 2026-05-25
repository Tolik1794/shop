<?php

namespace App\Dto\Api\Admin\Order;

use JsonSerializable;

class ProductSearchStockOptionDto implements JsonSerializable
{
	/**
	 * @param ProductSearchBatchLayerDto[] $batchLayers
	 */
	public function __construct(
		private readonly ?int $warehouseId,
		private readonly ?string $warehouseName,
		private readonly string $available,
		private readonly ?string $price,
		private readonly ?int $batchId = null,
		private readonly ?string $batchReceivedAt = null,
		private readonly ?string $priceSource = null,
		private readonly array $batchLayers = [],
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
			'batchId' => $this->batchId,
			'batchReceivedAt' => $this->batchReceivedAt,
			'priceSource' => $this->priceSource,
			'batchLayers' => $this->batchLayers,
		];
	}
}

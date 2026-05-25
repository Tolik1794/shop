<?php

namespace App\Service\Order;

use App\Entity\WarehouseStockBatch;

class OrderBatchPriceLayer
{
	public function __construct(
		private readonly WarehouseStockBatch $batch,
		private readonly string $available,
		private readonly ?string $price,
		private readonly string $priceSource,
	)
	{
	}

	public function getBatch(): WarehouseStockBatch
	{
		return $this->batch;
	}

	public function getAvailable(): string
	{
		return $this->available;
	}

	public function getPrice(): ?string
	{
		return $this->price;
	}

	public function getPriceSource(): string
	{
		return $this->priceSource;
	}
}

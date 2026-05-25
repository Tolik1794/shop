<?php

namespace App\Dto\Api\Admin\Order;

use App\Service\Order\OrderBatchPriceLayer;
use JsonSerializable;

class ProductSearchBatchLayerDto implements JsonSerializable
{
	public function __construct(
		private readonly int $batchId,
		private readonly string $available,
		private readonly ?string $price,
		private readonly string $priceSource,
		private readonly ?string $receivedAt,
	)
	{
	}

	public static function fromLayer(OrderBatchPriceLayer $layer): self
	{
		$batch = $layer->getBatch();

		return new self(
			batchId: (int) $batch->getId(),
			available: $layer->getAvailable(),
			price: $layer->getPrice(),
			priceSource: $layer->getPriceSource(),
			receivedAt: $batch->getReceivedAt()->format('Y-m-d'),
		);
	}

	public function getAvailable(): string
	{
		return $this->available;
	}

	public function getBatchId(): int
	{
		return $this->batchId;
	}

	public function getPrice(): ?string
	{
		return $this->price;
	}

	public function getPriceSource(): string
	{
		return $this->priceSource;
	}

	public function getReceivedAt(): ?string
	{
		return $this->receivedAt;
	}

	public function jsonSerialize(): array
	{
		return [
			'batchId' => $this->batchId,
			'available' => $this->available,
			'price' => $this->price,
			'priceSource' => $this->priceSource,
			'receivedAt' => $this->receivedAt,
		];
	}
}

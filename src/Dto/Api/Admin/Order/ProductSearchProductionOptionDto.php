<?php

namespace App\Dto\Api\Admin\Order;

use JsonSerializable;

class ProductSearchProductionOptionDto implements JsonSerializable
{
	public function __construct(
		private readonly ?string $price,
		private readonly string $label = 'For production',
	)
	{
	}

	public function jsonSerialize(): array
	{
		return [
			'type' => 'production',
			'label' => $this->label,
			'price' => $this->price,
		];
	}
}

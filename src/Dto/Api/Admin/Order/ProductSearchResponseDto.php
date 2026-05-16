<?php

namespace App\Dto\Api\Admin\Order;

use JsonSerializable;

class ProductSearchResponseDto implements JsonSerializable
{
	/**
	 * @param ProductSearchProductDto[] $products
	 */
	public function __construct(
		private readonly array $products,
		private readonly int $page,
		private readonly bool $hasMore,
	)
	{
	}

	public function jsonSerialize(): array
	{
		return [
			'products' => $this->products,
			'page' => $this->page,
			'hasMore' => $this->hasMore,
		];
	}
}

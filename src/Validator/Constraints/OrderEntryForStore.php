<?php

namespace App\Validator\Constraints;

use App\Entity\Store;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class OrderEntryForStore extends Constraint
{
	public string $invalidProductMessage = 'Select product from current store.';
	public string $invalidWarehouseMessage = 'Select warehouse from current store.';
	public string $insufficientStockMessage = 'Insufficient stock. Available quantity: {{ available }}.';

	public function __construct(
		public readonly Store $store,
		?array $groups = null,
		mixed $payload = null,
	)
	{
		parent::__construct([], $groups, $payload);
	}

	public function getTargets(): string
	{
		return self::CLASS_CONSTRAINT;
	}
}

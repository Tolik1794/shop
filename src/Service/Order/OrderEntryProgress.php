<?php

namespace App\Service\Order;

use App\Enum\OrderEntryFulfillmentState;

/**
 * Immutable, derived fulfillment progress of one order entry. Quantities are formatted decimal
 * strings (4 scale) consistent with the entity storage format. Nothing here is persisted.
 */
final readonly class OrderEntryProgress
{
	public function __construct(
		public string $ordered,
		public string $shipped,
		public string $returned,
		public string $canceled,
		public string $remaining,
		public string $returnable,
		public OrderEntryFulfillmentState $state,
	)
	{
	}

	public function hasRemaining(): bool
	{
		return (float) $this->remaining > 0.00005;
	}

	public function hasReturnable(): bool
	{
		return (float) $this->returnable > 0.00005;
	}

	public function hasShipped(): bool
	{
		return (float) $this->shipped > 0.00005;
	}

	public function hasReturned(): bool
	{
		return (float) $this->returned > 0.00005;
	}

	public function hasCanceled(): bool
	{
		return (float) $this->canceled > 0.00005;
	}
}

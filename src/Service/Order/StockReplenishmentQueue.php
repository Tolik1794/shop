<?php

namespace App\Service\Order;

/**
 * Request-scoped collector of products whose stock increased during the current request.
 *
 * Inventory posting enqueues affected products; {@see \App\EventSubscriber\StockReplenishmentSubscriber}
 * drains the queue on kernel.terminate (after the request transaction is committed) so waiting orders can be
 * re-evaluated without holding stock locks. Only scalar ids are stored to stay safe against detached entities.
 */
class StockReplenishmentQueue
{
	/** @var array<int, array<int, int>> map of storeId => list of productIds */
	private array $pending = [];

	/**
	 * @param int[] $productIds
	 */
	public function enqueue(int $storeId, array $productIds): void
	{
		$productIds = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));

		if ($productIds === []) {
			return;
		}

		$existing = $this->pending[$storeId] ?? [];
		$this->pending[$storeId] = array_values(array_unique(array_merge($existing, $productIds)));
	}

	/**
	 * Returns and clears the pending work.
	 *
	 * @return array<int, array<int, int>> map of storeId => list of productIds
	 */
	public function drain(): array
	{
		$pending = $this->pending;
		$this->pending = [];

		return $pending;
	}
}

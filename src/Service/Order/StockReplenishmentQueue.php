<?php

namespace App\Service\Order;

/**
 * Request-scoped collector of products whose stock increased during the current request.
 *
 * Inventory posting enqueues affected products and linked production completion enqueues its production order.
 * {@see \App\EventSubscriber\StockReplenishmentSubscriber} drains linked production first, then the general
 * product queue on kernel.terminate so priority allocation can lock Order before Stock.
 */
class StockReplenishmentQueue
{
	/** @var array<int, array<int, int>> map of storeId => list of productIds */
	private array $pending = [];

	/** @var array<int, int> */
	private array $productionOrderIds = [];

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

	public function enqueueProductionOrder(int $productionOrderId): void
	{
		if ($productionOrderId > 0) {
			$this->productionOrderIds[$productionOrderId] = $productionOrderId;
		}
	}

	/**
	 * @return int[]
	 */
	public function drainProductionOrders(): array
	{
		$ids = array_values($this->productionOrderIds);
		$this->productionOrderIds = [];

		return $ids;
	}
}

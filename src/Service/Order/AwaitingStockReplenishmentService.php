<?php

namespace App\Service\Order;

use App\Exception\ConcurrencyConflictException;
use App\Exception\StockOperationException;
use App\Manager\OrderManager;
use App\Repository\OrderRepository;
use RuntimeException;

/**
 * Re-evaluates orders waiting for stock once stock for their products has arrived. Each order is refreshed in
 * its own transaction (best-effort): a conflict on one order must not block the others, and a missed order is
 * picked up again on the next stock arrival.
 */
class AwaitingStockReplenishmentService
{
	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderManager $orderManager,
	)
	{
	}

	/**
	 * @param int[] $productIds
	 */
	public function replenish(int $storeId, array $productIds): void
	{
		if ($productIds === []) {
			return;
		}

		foreach ($this->orderRepository->findAwaitingStockByProducts($storeId, $productIds) as $order) {
			try {
				$this->orderManager->refreshAvailability($order);
			} catch (ConcurrencyConflictException | StockOperationException | RuntimeException) {
				// Best-effort: skip this order; it will be retried on the next stock arrival.
			}
		}
	}
}

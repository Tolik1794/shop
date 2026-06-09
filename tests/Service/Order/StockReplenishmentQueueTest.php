<?php

namespace App\Tests\Service\Order;

use App\Service\Order\StockReplenishmentQueue;
use PHPUnit\Framework\TestCase;

class StockReplenishmentQueueTest extends TestCase
{
	public function testProductionOrdersDrainBeforeGeneralProductsAndAreDeduplicated(): void
	{
		$queue = new StockReplenishmentQueue();
		$queue->enqueue(10, [3, 3, 4]);
		$queue->enqueueProductionOrder(25);
		$queue->enqueueProductionOrder(25);

		self::assertSame([25], $queue->drainProductionOrders());
		self::assertSame([10 => [3, 4]], $queue->drain());
		self::assertSame([], $queue->drainProductionOrders());
		self::assertSame([], $queue->drain());
	}
}

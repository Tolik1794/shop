<?php

namespace App\Tests\Service\Order;

use App\Entity\OrderEntry;
use App\Enum\OrderEntryFulfillmentState;
use App\Service\Order\OrderEntryProgressCalculator;
use PHPUnit\Framework\TestCase;

class OrderEntryProgressCalculatorTest extends TestCase
{
	private OrderEntryProgressCalculator $calculator;

	protected function setUp(): void
	{
		$this->calculator = new OrderEntryProgressCalculator();
	}

	public function testPendingWhenNothingShipped(): void
	{
		$progress = $this->calculator->calculate($this->entry('5.0000'));

		self::assertSame(OrderEntryFulfillmentState::PENDING, $progress->state);
		self::assertSame('5.0000', $progress->remaining);
		self::assertSame('0.0000', $progress->returnable);
	}

	public function testPartiallyShipped(): void
	{
		$progress = $this->calculator->calculate($this->entry('5.0000', shipped: '2.0000'));

		self::assertSame(OrderEntryFulfillmentState::PARTIALLY_SHIPPED, $progress->state);
		self::assertSame('3.0000', $progress->remaining);
		self::assertSame('2.0000', $progress->returnable);
	}

	public function testFullyShipped(): void
	{
		$progress = $this->calculator->calculate($this->entry('5.0000', shipped: '5.0000'));

		self::assertSame(OrderEntryFulfillmentState::SHIPPED, $progress->state);
		self::assertSame('0.0000', $progress->remaining);
		self::assertSame('5.0000', $progress->returnable);
	}

	public function testPartiallyReturned(): void
	{
		$progress = $this->calculator->calculate($this->entry('5.0000', shipped: '5.0000', returned: '2.0000'));

		self::assertSame(OrderEntryFulfillmentState::PARTIALLY_RETURNED, $progress->state);
		self::assertSame('3.0000', $progress->returnable);
	}

	public function testFullyReturned(): void
	{
		$progress = $this->calculator->calculate($this->entry('5.0000', shipped: '5.0000', returned: '5.0000'));

		self::assertSame(OrderEntryFulfillmentState::RETURNED, $progress->state);
		self::assertSame('0.0000', $progress->returnable);
	}

	public function testRefusedWhenWholePositionCanceled(): void
	{
		$progress = $this->calculator->calculate($this->entry('5.0000', canceled: '5.0000'));

		self::assertSame(OrderEntryFulfillmentState::REFUSED, $progress->state);
		self::assertSame('0.0000', $progress->remaining);
	}

	public function testCanceledRemainderCountsShippedAsComplete(): void
	{
		// Shipped 3 of 5, refused the remaining 2: the expected quantity drops to 3, so the line is fully shipped.
		$progress = $this->calculator->calculate($this->entry('5.0000', shipped: '3.0000', canceled: '2.0000'));

		self::assertSame(OrderEntryFulfillmentState::SHIPPED, $progress->state);
		self::assertSame('0.0000', $progress->remaining);
	}

	public function testRemainingExcludesCanceledQuantity(): void
	{
		$progress = $this->calculator->calculate($this->entry('10.0000', shipped: '4.0000', canceled: '2.0000'));

		self::assertSame(OrderEntryFulfillmentState::PARTIALLY_SHIPPED, $progress->state);
		self::assertSame('4.0000', $progress->remaining);
	}

	private function entry(string $quantity, ?string $shipped = null, ?string $returned = null, ?string $canceled = null): OrderEntry
	{
		return (new OrderEntry())
			->setQuantity($quantity)
			->setShippedQuantity($shipped)
			->setReturnedQuantity($returned)
			->setCanceledQuantity($canceled);
	}
}

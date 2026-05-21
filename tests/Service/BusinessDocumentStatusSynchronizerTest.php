<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\ProductKindEnum;
use App\Repository\WarehouseStockRepository;
use App\Service\BusinessDocumentStatusSynchronizer;
use App\Workflow\History\GenericStatusHistoryRecorder;
use App\Workflow\History\OrderHistoryRecorder;
use App\Workflow\TransitionContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BusinessDocumentStatusSynchronizerTest extends TestCase
{
	private WarehouseStockRepository&MockObject $warehouseStockRepository;
	private GenericStatusHistoryRecorder&MockObject $statusHistoryRecorder;
	private OrderHistoryRecorder&MockObject $orderHistoryRecorder;
	private BusinessDocumentStatusSynchronizer $synchronizer;

	protected function setUp(): void
	{
		$this->warehouseStockRepository = $this
			->getMockBuilder(WarehouseStockRepository::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneByProductAndWarehouse'])
			->getMock();
		$this->statusHistoryRecorder = $this->createMock(GenericStatusHistoryRecorder::class);
		$this->orderHistoryRecorder = $this->createMock(OrderHistoryRecorder::class);
		$this->synchronizer = new BusinessDocumentStatusSynchronizer(
			$this->warehouseStockRepository,
			$this->statusHistoryRecorder,
			$this->orderHistoryRecorder,
		);
	}

	/**
	 * @return iterable<string, array{0: string, 1: string, 2: string, 3: bool}>
	 */
	public static function derivedOrderStatuses(): iterable
	{
		yield 'awaiting stock' => ['0.0000', '0.0000', OrderStatus::AWAITING_STOCK->value, false];
		yield 'ready to ship' => ['0.0000', '0.0000', OrderStatus::READY_TO_SHIP->value, true];
		yield 'partially shipped' => ['2.0000', '0.0000', OrderStatus::PARTIALLY_SHIPPED->value, false];
		yield 'shipped' => ['5.0000', '0.0000', OrderStatus::SHIPPED->value, false];
		yield 'partially returned' => ['5.0000', '2.0000', OrderStatus::PARTIALLY_RETURNED->value, false];
		yield 'returned' => ['5.0000', '5.0000', OrderStatus::RETURNED->value, false];
	}

	#[DataProvider('derivedOrderStatuses')]
	public function testRecordsOrderHistoryForDerivedOrderStatusChanges(
		string $shippedQuantity,
		string $returnedQuantity,
		string $expectedStatus,
		bool $stockAvailable,
	): void {
		$order = $this->orderWithEntry($shippedQuantity, $returnedQuantity);
		$context = TransitionContext::system();

		if ($stockAvailable) {
			$this->warehouseStockRepository->method('findOneByProductAndWarehouse')
				->willReturn((new WarehouseStock())->setQuantityOnHand('5.0000'));
		}

		$this->orderHistoryRecorder->expects($this->once())
			->method('recordStatusChanged')
			->with($order, 'sync_progress', OrderStatus::CONFIRMED->value, $expectedStatus, $context);

		$this->synchronizer->syncOrder($order, $context);

		self::assertSame($expectedStatus, $order->getStatus()->value);
	}

	public function testDoesNotRecordOrderHistoryWhenDerivedStatusDoesNotChange(): void
	{
		$order = $this->orderWithEntry('5.0000', '0.0000')
			->setStatus(OrderStatus::SHIPPED);

		$this->orderHistoryRecorder->expects($this->never())->method('recordStatusChanged');

		$this->synchronizer->syncOrder($order, TransitionContext::system());
	}

	private function orderWithEntry(string $shippedQuantity, string $returnedQuantity): Order
	{
		$product = (new Product())
			->setProductKind(ProductKindEnum::FINISHED_PRODUCT)
			->setName('Product')
			->setCode('product-' . uniqid());
		$warehouse = new Warehouse();
		$entry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setShippedQuantity($shippedQuantity)
			->setReturnedQuantity($returnedQuantity)
			->setCanceledQuantity('0.0000');

		return (new Order())
			->setNumber('SO-' . uniqid())
			->setStatus(OrderStatus::CONFIRMED)
			->addOrderEntry($entry);
	}
}

<?php

namespace App\Tests\Manager;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderHistory;
use App\Entity\OrderHistorySource;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\ProductDiscountTarget;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Enum\PaymentStatusEnum;
use App\Enum\OrderDiscountModeEnum;
use App\Enum\ProductDiscountTargetTypeEnum;
use App\Enum\ProductKindEnum;
use App\Manager\OrderManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager);
	}

	public function testSaveOrderCopiesProductSnapshotAndRecalculatesTotals(): void
	{
		$currency = $this->persistCurrency('O' . substr(uniqid(), -2), 'Order currency');
		$store = $this->persistStore('order-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('2.0000')
			->setUnitPrice('15.0000');
		$order->addOrderEntry($orderEntry);

		$this->orderManager->saveOrder($order);
		$this->entityManager->refresh($order);

		self::assertSame('30.0000', $order->getTotalAmount());
		self::assertSame('30.0000', $order->getTotalAmountBase());
		self::assertNull($order->getDiscountAmount());
		self::assertSame($product->getName(), $orderEntry->getProductNameSnapshot());
		self::assertSame($product->getUnit()?->getCode(), $orderEntry->getUnitCodeSnapshot());

		$eventKeys = array_map(
			static fn (OrderHistory $history): string => $history->getEventKey(),
			$this->entityManager->getRepository(OrderHistory::class)->findBy(['order' => $order]),
		);

		self::assertContains('order.created', $eventKeys);
		self::assertContains('order.entry_added', $eventKeys);
	}

	public function testSaveOrderInitializesMissingEntryPriceInOrderCurrency(): void
	{
		$baseCurrency = $this->persistCurrency('B' . substr(uniqid(), -2), 'Base currency');
		$orderCurrency = $this->persistCurrency('D' . substr(uniqid(), -2), 'Document currency');
		$store = $this->persistStore('order-price-' . uniqid(), $baseCurrency);
		$product = $this->persistProduct($store);
		$this->persistExchangeRate($orderCurrency, $baseCurrency, $store, '2.00000000');
		$order = $this->orderManager->createDraft($store)->setCurrency($orderCurrency);
		$order->addOrderEntry((new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000'));

		$this->orderManager->saveOrder($order);

		self::assertSame('5.0000', $order->getOrderEntries()->first()->getUnitPrice());
	}

	public function testSaveOrderAppliesDefaultProductDiscountRule(): void
	{
		$currency = $this->persistCurrency('G' . substr(uniqid(), -2), 'Discount currency');
		$store = $this->persistStore('order-discount-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$this->persistProductDiscountRule($store, $product, '10.0000', true);
		$order = $this->orderManager->createDraft($store);
		$order->addOrderEntry((new OrderEntry())
			->setProduct($product)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000'));

		$this->orderManager->saveOrder($order);
		$orderEntry = $order->getOrderEntries()->first();

		self::assertSame('18.0000', $order->getTotalAmount());
		self::assertSame('2.0000', $orderEntry->getDiscountAmount());
		self::assertSame('10.0000', $orderEntry->getDiscountPercent());
		self::assertSame(OrderDiscountModeEnum::RULE, $orderEntry->getDiscountMode());
		self::assertSame('Default discount', $orderEntry->getDiscountRuleNameSnapshot());
	}

	public function testSaveOrderBlocksDiscountBelowCostWithoutOverridePermission(): void
	{
		$currency = $this->persistCurrency('H' . substr(uniqid(), -2), 'Below cost currency');
		$store = $this->persistStore('order-below-cost-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '3.0000');
		$warehouseStock->setAverageCost('9.0000');
		$this->entityManager->flush();
		$this->persistProductDiscountRule($store, $product, '20.0000', true);
		$order = $this->orderManager->createDraft($store);
		$order->addOrderEntry((new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('1.0000')
			->setUnitPrice('10.0000'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Discounted order line total is below cost');

		$this->orderManager->saveOrder($order);
	}

	public function testConfirmMovesDraftOrderWithoutStockToAwaitingStock(): void
	{
		$currency = $this->persistCurrency('S' . substr(uniqid(), -2), 'Sales currency');
		$store = $this->persistStore('order-confirm-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);

		self::assertSame(OrderStatus::AWAITING_STOCK, $order->getStatus());
		self::assertNotNull($this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.status_changed',
		]));
	}

	public function testConfirmReservesStockBackedOrderEntriesAndMarksReadyToShip(): void
	{
		$currency = $this->persistCurrency('T' . substr(uniqid(), -2), 'Reservation currency');
		$store = $this->persistStore('order-reservation-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '3.0000');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);
		$this->entityManager->refresh($warehouseStock);

		$reservation = $this->entityManager->getRepository(StockReservation::class)->findOneBy([
			'orderEntry' => $orderEntry,
			'warehouseStock' => $warehouseStock,
		]);

		self::assertInstanceOf(StockReservation::class, $reservation);
		self::assertSame(StockReservationStatus::ACTIVE, $reservation->getStatus());
		self::assertSame('2.0000', $reservation->getQuantity());
		self::assertSame('2.0000', $warehouseStock->getReservedQuantity());
		self::assertSame(OrderStatus::READY_TO_SHIP, $order->getStatus());
	}

	public function testSaveOrderWithNewBatchBackedEntryDoesNotQueryReservationsBeforeEntryHasId(): void
	{
		$currency = $this->persistCurrency('G' . substr(uniqid(), -2), 'New batch order currency');
		$store = $this->persistStore('order-new-batch-entry-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '3.0000');
		$batch = $this->persistWarehouseStockBatch($warehouseStock, '3.0000', '14.0000');
		$order = $this->orderManager->createDraft($store);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setWarehouseStockBatch($batch)
			->setQuantity('2.0000')
			->setUnitPrice('0.0000');
		$order->addOrderEntry($orderEntry);

		$this->orderManager->saveOrder($order);

		self::assertNotNull($orderEntry->getId());
		self::assertSame('14.0000', $orderEntry->getUnitPrice());
	}

	public function testConfirmWithPartialBackorderMovesOrderToAwaitingStock(): void
	{
		$currency = $this->persistCurrency('W' . substr(uniqid(), -2), 'Backorder currency');
		$store = $this->persistStore('order-backorder-' . uniqid(), $currency)
			->setAllowBackorders(true);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '1.0000');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(OrderStatus::AWAITING_STOCK, $order->getStatus());
		self::assertSame('1.0000', $warehouseStock->getReservedQuantity());
	}

	public function testCancelMovesOrderToCanceledAndStoresTransitionTime(): void
	{
		$currency = $this->persistCurrency('C' . substr(uniqid(), -2), 'Cancel currency');
		$store = $this->persistStore('order-cancel-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$this->orderManager->cancel($order);

		self::assertSame(OrderStatus::CANCELED, $order->getStatus());
		self::assertNotNull($order->getCanceledAt());
	}

	public function testReturnToDraftMovesAwaitingStockOrderBackToDraft(): void
	{
		$currency = $this->persistCurrency('R' . substr(uniqid(), -2), 'Rollback currency');
		$store = $this->persistStore('order-return-to-draft-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);
		$this->orderManager->returnToDraft($order);

		self::assertSame(OrderStatus::DRAFT, $order->getStatus());
	}

	public function testReturnToDraftReleasesActiveReservations(): void
	{
		$currency = $this->persistCurrency('K' . substr(uniqid(), -2), 'Release reservation currency');
		$store = $this->persistStore('order-release-reservation-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '3.0000');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);
		$reservation = $this->entityManager->getRepository(StockReservation::class)->findOneBy([
			'orderEntry' => $orderEntry,
			'warehouseStock' => $warehouseStock,
		]);
		self::assertInstanceOf(StockReservation::class, $reservation);

		$this->orderManager->returnToDraft($order);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(OrderStatus::DRAFT, $order->getStatus());
		self::assertSame(StockReservationStatus::CANCELED, $reservation->getStatus());
		self::assertSame('0.0000', $warehouseStock->getReservedQuantity());
	}

	public function testReturnToDraftDoesNotRestoreCanceledOrderYet(): void
	{
		$currency = $this->persistCurrency('X' . substr(uniqid(), -2), 'Canceled rollback currency');
		$store = $this->persistStore('order-canceled-rollback-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$this->orderManager->cancel($order);

		$this->expectException(RuntimeException::class);

		$this->orderManager->returnToDraft($order);
	}

	public function testMarkDeliveredMovesShippedOrderToDelivered(): void
	{
		$currency = $this->persistCurrency('L' . substr(uniqid(), -2), 'Delivery currency');
		$store = $this->persistStore('order-delivered-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$order->setStatus(OrderStatus::SHIPPED);
		$this->entityManager->flush();

		$this->orderManager->markDelivered($order);

		self::assertSame(OrderStatus::DELIVERED, $order->getStatus());
		$history = $this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.status_changed',
		], ['id' => 'DESC']);

		self::assertInstanceOf(OrderHistory::class, $history);
		self::assertSame([
			'status' => ['from' => OrderStatus::SHIPPED->value, 'to' => OrderStatus::DELIVERED->value],
		], $history->getChanges());
	}

	public function testCompleteMovesDeliveredOrderToCompleted(): void
	{
		$currency = $this->persistCurrency('F' . substr(uniqid(), -2), 'Complete currency');
		$store = $this->persistStore('order-complete-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$order
			->setStatus(OrderStatus::DELIVERED)
			->setPaymentStatus(PaymentStatusEnum::PAID);
		$this->entityManager->flush();

		$this->orderManager->complete($order);

		self::assertSame(OrderStatus::COMPLETED, $order->getStatus());
		$history = $this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.status_changed',
		], ['id' => 'DESC']);

		self::assertInstanceOf(OrderHistory::class, $history);
		self::assertSame([
			'status' => ['from' => OrderStatus::DELIVERED->value, 'to' => OrderStatus::COMPLETED->value],
		], $history->getChanges());
	}

	public function testCompleteIsBlockedBeforeFullPayment(): void
	{
		$currency = $this->persistCurrency('Q' . substr(uniqid(), -2), 'Unpaid complete currency');
		$store = $this->persistStore('order-complete-unpaid-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$order->setStatus(OrderStatus::DELIVERED);
		$this->entityManager->flush();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Document must be fully paid before completion.');

		$this->orderManager->complete($order);
	}

	public function testCompleteIsBlockedBeforeDelivery(): void
	{
		$currency = $this->persistCurrency('Y' . substr(uniqid(), -2), 'Blocked complete currency');
		$store = $this->persistStore('order-complete-blocked-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$order->setStatus(OrderStatus::SHIPPED);
		$this->entityManager->flush();

		$this->expectException(RuntimeException::class);

		$this->orderManager->complete($order);
	}

	public function testCancelIsBlockedAfterCompletion(): void
	{
		$currency = $this->persistCurrency('Z' . substr(uniqid(), -2), 'Completed cancel currency');
		$store = $this->persistStore('order-cancel-completed-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$order->setStatus(OrderStatus::COMPLETED);
		$this->entityManager->flush();

		$this->expectException(RuntimeException::class);

		$this->orderManager->cancel($order);
	}

	public function testRollbackStatusRestoresCanceledOrderAndReservations(): void
	{
		$currency = $this->persistCurrency('V' . substr(uniqid(), -2), 'Canceled rollback reservation currency');
		$store = $this->persistStore('order-canceled-rollback-reservation-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '3.0000');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);
		$this->orderManager->cancel($order);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(OrderStatus::CANCELED, $order->getStatus());
		self::assertNotNull($order->getCanceledAt());
		self::assertSame('0.0000', $warehouseStock->getReservedQuantity());
		self::assertTrue($this->orderManager->canRollbackStatus($order));

		$this->orderManager->rollbackStatus($order);
		$this->entityManager->refresh($warehouseStock);

		$activeReservations = $this->entityManager->getRepository(StockReservation::class)->findBy([
			'orderEntry' => $orderEntry,
			'status' => StockReservationStatus::ACTIVE,
		]);

		self::assertSame(OrderStatus::READY_TO_SHIP, $order->getStatus());
		self::assertNull($order->getCanceledAt());
		self::assertSame('2.0000', $warehouseStock->getReservedQuantity());
		self::assertCount(1, $activeReservations);
	}

	public function testRollbackStatusFromShippedKeepsRollbackTargetInsteadOfSyncingForward(): void
	{
		$currency = $this->persistCurrency('H' . substr(uniqid(), -2), 'Shipped rollback currency');
		$store = $this->persistStore('order-shipped-rollback-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, '5.0000');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000')
			->setShippedQuantity('2.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->recordStatusChange($order, OrderStatus::DRAFT, OrderStatus::READY_TO_SHIP);
		$this->recordStatusChange($order, OrderStatus::READY_TO_SHIP, OrderStatus::SHIPPED);

		$this->orderManager->rollbackStatus($order);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(OrderStatus::READY_TO_SHIP, $order->getStatus());
		self::assertSame('2.0000', $warehouseStock->getReservedQuantity());
	}

	public function testRollbackStatusRestoresCompletedOrderToDelivered(): void
	{
		$currency = $this->persistCurrency('M' . substr(uniqid(), -2), 'Completed rollback currency');
		$store = $this->persistStore('order-completed-rollback-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$order
			->setStatus(OrderStatus::DELIVERED)
			->setPaymentStatus(PaymentStatusEnum::PAID);
		$this->entityManager->flush();
		$this->orderManager->complete($order);
		$completedHistory = $this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.status_changed',
		], ['id' => 'DESC']);

		self::assertInstanceOf(OrderHistory::class, $completedHistory);

		$this->orderManager->rollbackStatus($order);

		$history = $this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.status_changed',
		], ['id' => 'DESC']);

		self::assertSame(OrderStatus::DELIVERED, $order->getStatus());
		self::assertInstanceOf(OrderHistory::class, $history);
		self::assertSame([
			'status' => ['from' => OrderStatus::COMPLETED->value, 'to' => OrderStatus::DELIVERED->value],
		], $history->getChanges());
		self::assertSame([
			'transition' => 'rollback_status',
			'rolled_back_history_id' => $completedHistory->getId(),
		], $history->getPayload());
	}

	public function testRollbackStatusWalksBackThroughUnrolledStatusHistory(): void
	{
		$currency = $this->persistCurrency('U' . substr(uniqid(), -2), 'Sequential rollback currency');
		$store = $this->persistStore('order-sequential-rollback-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$this->recordStatusChange($order, OrderStatus::DRAFT, OrderStatus::SHIPPED);
		$order->setPaymentStatus(PaymentStatusEnum::PAID);
		$this->entityManager->flush();
		$this->orderManager->markDelivered($order);
		$this->orderManager->complete($order);

		self::assertSame(OrderStatus::COMPLETED, $order->getStatus());
		self::assertTrue($this->orderManager->canRollbackStatus($order));

		$this->orderManager->rollbackStatus($order);

		self::assertSame(OrderStatus::DELIVERED, $order->getStatus());
		self::assertTrue($this->orderManager->canRollbackStatus($order));

		$this->orderManager->rollbackStatus($order);

		self::assertSame(OrderStatus::SHIPPED, $order->getStatus());
		self::assertTrue($this->orderManager->canRollbackStatus($order));

		$this->orderManager->rollbackStatus($order);

		self::assertSame(OrderStatus::DRAFT, $order->getStatus());
		self::assertFalse($this->orderManager->canRollbackStatus($order));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Order has no previous status to rollback to.');

		$this->orderManager->rollbackStatus($order);
	}

	public function testRollbackStatusRequiresPreviousStatusHistory(): void
	{
		$currency = $this->persistCurrency('N' . substr(uniqid(), -2), 'No rollback history currency');
		$store = $this->persistStore('order-no-rollback-history-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		self::assertFalse($this->orderManager->canRollbackStatus($order));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Order has no previous status to rollback to.');

		$this->orderManager->rollbackStatus($order);
	}

	public function testSaveOrderRecordsOnlyRealDecimalEntryChanges(): void
	{
		$currency = $this->persistCurrency('D' . substr(uniqid(), -2), 'Decimal history currency');
		$store = $this->persistStore('order-decimal-history-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000')
			->setUnitPrice('99.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);

		$orderEntry->setUnitPrice('99');
		$this->orderManager->saveOrder($order);

		self::assertCount(0, $this->entityManager->getRepository(OrderHistory::class)->findBy([
			'order' => $order,
			'eventKey' => 'order.entry_updated',
		]));

		$orderEntry->setUnitPrice('100');
		$this->orderManager->saveOrder($order);

		$entryUpdatedHistory = $this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.entry_updated',
		]);

		self::assertInstanceOf(OrderHistory::class, $entryUpdatedHistory);
		self::assertSame([
			'unitPrice' => ['from' => '99', 'to' => '100'],
		], $entryUpdatedHistory->getChanges());
	}

	private function recordStatusChange(Order $order, OrderStatus $fromStatus, OrderStatus $toStatus): void
	{
		$order->setStatus($toStatus);

		$history = (new OrderHistory())
			->setOrder($order)
			->setEventKey('order.status_changed')
			->setSource(OrderHistorySource::SYSTEM)
			->setTitle('Order status changed')
			->setDescription(sprintf('Status changed from %s to %s.', $fromStatus->value, $toStatus->value))
			->setChanges(['status' => ['from' => $fromStatus->value, 'to' => $toStatus->value]]);

		$this->entityManager->persist($history);
		$this->entityManager->flush();
	}

	private function persistCurrency(string $code, string $name): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($name)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}

	private function persistStore(string $slug, Currency $baseCurrency): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($baseCurrency);

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}

	private function persistProduct(Store $store): Product
	{
		$category = (new Category())
			->setName('Category ' . uniqid())
			->setLevel(1)
			->setStore($store);
		$unit = (new Unit())
			->setName('Piece ' . uniqid())
			->setCode('pc' . substr(uniqid(), -4))
			->setPrecision(0)
			->setStore($store);
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($unit)
			->setProductKind(ProductKindEnum::FINISHED_PRODUCT)
			->setName('Product ' . uniqid())
			->setCode('product-' . uniqid())
			->setBaseSalePrice('10.0000')
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}

	private function persistWarehouse(Store $store): Warehouse
	{
		$warehouse = (new Warehouse())
			->setName('Warehouse ' . uniqid())
			->setStore($store);

		$this->entityManager->persist($warehouse);
		$this->entityManager->flush();

		return $warehouse;
	}

	private function persistWarehouseStock(Warehouse $warehouse, Product $product, string $quantityOnHand): WarehouseStock
	{
		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setQuantityOnHand($quantityOnHand);

		$this->entityManager->persist($warehouseStock);
		$this->entityManager->flush();

		return $warehouseStock;
	}

	private function persistWarehouseStockBatch(WarehouseStock $warehouseStock, string $quantity, string $salePrice): WarehouseStockBatch
	{
		$batch = (new WarehouseStockBatch())
			->setInitialQuantity($quantity)
			->setRemainingQuantity($quantity)
			->setUnitCost('10.0000')
			->setSalePrice($salePrice)
			->setReceivedAt(new DateTimeImmutable('2026-01-01 00:00:00'));
		$warehouseStock->addWarehouseStockBatch($batch);

		$this->entityManager->persist($batch);
		$this->entityManager->flush();

		return $batch;
	}

	private function persistProductDiscountRule(Store $store, Product $product, string $percent, bool $isDefault): ProductDiscountRule
	{
		$rule = (new ProductDiscountRule())
			->setStore($store)
			->setName('Default discount')
			->setPercent($percent)
			->setIsDefault($isDefault);
		$rule->addTarget((new ProductDiscountTarget())
			->setTargetType(ProductDiscountTargetTypeEnum::PRODUCT)
			->setProduct($product));

		$this->entityManager->persist($rule);
		$this->entityManager->flush();

		return $rule;
	}

	private function persistExchangeRate(Currency $fromCurrency, Currency $toCurrency, Store $store, string $rate): ExchangeRate
	{
		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate($rate)
			->setValidFrom(new DateTimeImmutable('2026-01-01 00:00:00'));

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();

		return $exchangeRate;
	}
}

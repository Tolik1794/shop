<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Entity\StockMovement;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Service\InventoryPostingService;
use App\Service\StockReservationService;
use App\Service\StockReservationReconciler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StockReservationServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private StockReservationService $stockReservationService;
	private StockReservationReconciler $stockReservationReconciler;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->stockReservationService = static::getContainer()->get(StockReservationService::class);
		$this->stockReservationReconciler = static::getContainer()->get(StockReservationReconciler::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->stockReservationService, $this->stockReservationReconciler);
	}

	public function testReserveCreatesActiveReservationAndUpdatesAggregate(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('5.0000', '0.0000', '2.0000');

		$reservation = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '2.0000');
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(StockReservationStatus::ACTIVE, $reservation->getStatus());
		self::assertSame('2.0000', $reservation->getQuantity());
		self::assertSame('2.0000', $warehouseStock->getReservedQuantity());
	}

	public function testOverReservationIsBlocked(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('2.0000', '0.0000', '3.0000');

		$this->expectException(StockOperationException::class);

		$this->stockReservationService->reserve($orderEntry, $warehouseStock, '3.0000');
	}

	public function testReleaseRemovesActiveReservationFromAggregate(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('5.0000', '0.0000', '2.0000');
		$reservation = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '2.0000');

		$this->stockReservationService->release($reservation);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(StockReservationStatus::CANCELED, $reservation->getStatus());
		self::assertSame('0.0000', $warehouseStock->getReservedQuantity());
	}

	public function testCompleteRemovesActiveReservationFromAggregate(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('5.0000', '0.0000', '2.0000');
		$reservation = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '2.0000');

		$this->stockReservationService->complete($reservation);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(StockReservationStatus::COMPLETED, $reservation->getStatus());
		self::assertSame('0.0000', $warehouseStock->getReservedQuantity());
	}

	public function testCompleteForOrderEntryKeepsUnshippedReservationBalanceActive(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('5.0000', '0.0000', '5.0000');
		$reservation = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '5.0000');

		$this->stockReservationService->completeForOrderEntry($orderEntry, '2.0000');
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(StockReservationStatus::ACTIVE, $reservation->getStatus());
		self::assertSame('3.0000', $reservation->getQuantity());
		self::assertSame('3.0000', $warehouseStock->getReservedQuantity());
		self::assertSame('3.0000', $this->stockReservationService->getActiveQuantityForOrderEntry($orderEntry));
	}

	public function testCompleteForOrderEntryCompletesMultipleReservationsWithoutRestoringAggregate(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('2.0000', '0.0000', '2.0000');
		$first = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '1.0000');
		$second = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '1.0000');

		$this->stockReservationService->completeForOrderEntry($orderEntry, '2.0000');
		$this->entityManager->clear();
		$warehouseStock = $this->entityManager->find(WarehouseStock::class, $warehouseStock->getId());

		self::assertSame(StockReservationStatus::COMPLETED, $this->entityManager->find(StockReservation::class, $first->getId())?->getStatus());
		self::assertSame(StockReservationStatus::COMPLETED, $this->entityManager->find(StockReservation::class, $second->getId())?->getStatus());
		self::assertSame('0.0000', $warehouseStock?->getReservedQuantity());
	}

	public function testReleaseForOrderEntryCancelsMultipleReservationsWithoutRestoringAggregate(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('2.0000', '0.0000', '2.0000');
		$first = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '1.0000');
		$second = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '1.0000');

		$this->stockReservationService->releaseForOrderEntry($orderEntry, '2.0000');
		$this->entityManager->clear();
		$warehouseStock = $this->entityManager->find(WarehouseStock::class, $warehouseStock->getId());

		self::assertSame(StockReservationStatus::CANCELED, $this->entityManager->find(StockReservation::class, $first->getId())?->getStatus());
		self::assertSame(StockReservationStatus::CANCELED, $this->entityManager->find(StockReservation::class, $second->getId())?->getStatus());
		self::assertSame('0.0000', $warehouseStock?->getReservedQuantity());
	}

	public function testPostingMultipleShipmentLinesConsumesBatchesAndCompletesReservations(): void
	{
		[$firstEntry, $warehouseStock] = $this->createOrderEntryAndStock('3.0000', '0.0000', '1.0000');
		$order = $firstEntry->getOrder();
		$product = $firstEntry->getProduct();
		$warehouse = $firstEntry->getWarehouse();
		self::assertInstanceOf(Order::class, $order);
		self::assertInstanceOf(Product::class, $product);
		self::assertInstanceOf(Warehouse::class, $warehouse);

		$entries = [$firstEntry];
		for ($index = 0; $index < 2; $index++) {
			$entry = (new OrderEntry())
				->setProduct($product)
				->setWarehouse($warehouse)
				->setQuantity('1.0000')
				->setUnitPrice('10.0000')
				->setUnitPriceBase('10.0000')
				->setTotalPrice('10.0000')
				->setTotalPriceBase('10.0000')
				->setProductNameSnapshot((string) $product->getName())
				->setProductCodeSnapshot((string) $product->getCode())
				->setUnitCodeSnapshot('pc')
				->setUnitNameSnapshot('Piece');
			$order->addOrderEntry($entry);
			$this->entityManager->persist($entry);
			$entries[] = $entry;
		}

		$batches = [];
		foreach ($entries as $index => $entry) {
			$batch = (new WarehouseStockBatch())
				->setWarehouseStock($warehouseStock)
				->setInitialQuantity('1.0000')
				->setRemainingQuantity('1.0000')
				->setUnitCost('10.0000')
				->setReceivedAt(new DateTimeImmutable(sprintf('2026-01-%02d', $index + 1)));
			$warehouseStock->addWarehouseStockBatch($batch);
			$entry->setWarehouseStockBatch($batch);
			$this->entityManager->persist($batch);
			$batches[] = $batch;
		}
		$this->entityManager->flush();

		foreach ($entries as $index => $entry) {
			$this->stockReservationService->reserveBatch($entry, $batches[$index], '1.0000');
		}

		$document = (new InventoryDocument())
			->setStore($order->getStore())
			->setOrder($order)
			->setNumber('SHP-' . uniqid())
			->setType(InventoryDocumentType::SALE_SHIPMENT)
			->setCurrency($order->getCurrency())
			->setExchangeRateToBase('1.00000000');
		foreach ($entries as $entry) {
			$document->addLine((new InventoryDocumentLine())
				->setProduct($product)
				->setWarehouse($warehouse)
				->setOrderEntry($entry)
				->setDirection(InventoryDirection::OUT)
				->setQuantity('1.0000')
				->setUnitPrice('10.0000')
				->setUnitPriceBase('10.0000'));
		}
		$this->entityManager->persist($document);
		$this->entityManager->flush();

		static::getContainer()->get(InventoryPostingService::class)->post($document);
		$stockId = $warehouseStock->getId();
		$this->entityManager->clear();
		$warehouseStock = $this->entityManager->find(WarehouseStock::class, $stockId);
		$movements = $this->entityManager->getRepository(StockMovement::class)->findBy(
			['warehouseStock' => $warehouseStock],
			['createdAt' => 'ASC', 'id' => 'ASC'],
		);

		self::assertSame('0.0000', $warehouseStock?->getQuantityOnHand());
		self::assertSame('0.0000', $warehouseStock?->getReservedQuantity());
		self::assertCount(3, $warehouseStock?->getWarehouseStockBatches() ?? []);
		self::assertSame(['0.0000', '0.0000', '0.0000'], array_map(
			static fn (WarehouseStockBatch $batch): ?string => $batch->getRemainingQuantity(),
			$warehouseStock?->getWarehouseStockBatches()->toArray() ?? [],
		));
		self::assertSame(['2.0000', '1.0000', '0.0000'], array_map(
			static fn (StockMovement $movement): ?string => $movement->getBalanceAfter(),
			$movements,
		));
	}

	public function testExpiredReservationIsReleasedFromAggregate(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('5.0000', '0.0000', '2.0000');
		$reservation = $this->stockReservationService->reserve(
			$orderEntry,
			$warehouseStock,
			'2.0000',
			new DateTimeImmutable('2026-01-01 00:00:00')
		);

		$count = $this->stockReservationService->expireOldReservations(new DateTimeImmutable('2026-01-02 00:00:00'));
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(1, $count);
		self::assertSame(StockReservationStatus::EXPIRED, $reservation->getStatus());
		self::assertSame('0.0000', $warehouseStock->getReservedQuantity());
	}

	public function testBackorderReservesOnlyAvailableQuantity(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('2.0000', '0.0000', '5.0000', true);

		$reservation = $this->stockReservationService->reserveForOrderEntry($orderEntry, $warehouseStock);
		$this->entityManager->refresh($warehouseStock);

		self::assertNotNull($reservation);
		self::assertSame('2.0000', $reservation->getQuantity());
		self::assertSame('2.0000', $warehouseStock->getReservedQuantity());
		self::assertSame('2.0000', $this->stockReservationService->getActiveQuantityForOrderEntry($orderEntry));
	}

	public function testReserveBatchBindsReservationToExactBatch(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('2.0000', '0.0000', '1.0000');
		$batch = (new WarehouseStockBatch())
			->setWarehouseStock($warehouseStock)
			->setInitialQuantity('2.0000')
			->setRemainingQuantity('2.0000')
			->setUnitCost('10.0000')
			->setReceivedAt(new DateTimeImmutable());
		$warehouseStock->addWarehouseStockBatch($batch);
		$this->entityManager->persist($batch);
		$this->entityManager->flush();

		$reservation = $this->stockReservationService->reserveBatch($orderEntry, $batch, '1.0000');

		self::assertSame($batch, $reservation->getWarehouseStockBatch());
		self::assertSame('1.0000', $warehouseStock->getReservedQuantity());
	}

	public function testReconcilerDryRunThenCancelsStaleActiveReservation(): void
	{
		[$orderEntry, $warehouseStock] = $this->createOrderEntryAndStock('2.0000', '0.0000', '1.0000');
		$active = $this->stockReservationService->reserve($orderEntry, $warehouseStock, '1.0000');
		$completed = (new StockReservation())
			->setOrderEntry($orderEntry)
			->setWarehouseStock($warehouseStock)
			->setQuantity('1.0000')
			->setStatus(StockReservationStatus::COMPLETED);
		$orderEntry->setShippedQuantity('1.0000')->addStockReservation($completed);
		$warehouseStock->addStockReservation($completed);
		$this->entityManager->persist($completed);
		$this->entityManager->flush();

		$dryRun = $this->stockReservationReconciler->reconcile(orderId: $orderEntry->getOrder()?->getId());

		self::assertCount(1, $dryRun['entries']);
		self::assertSame('1.0000', $dryRun['entries'][0]['cancel']);
		self::assertSame(StockReservationStatus::ACTIVE, $active->getStatus());
		self::assertSame('1.0000', $warehouseStock->getReservedQuantity());

		$applied = $this->stockReservationReconciler->reconcile(orderId: $orderEntry->getOrder()?->getId(), apply: true);
		$this->entityManager->refresh($active);
		$this->entityManager->refresh($warehouseStock);

		self::assertCount(1, $applied['entries']);
		self::assertSame(StockReservationStatus::CANCELED, $active->getStatus());
		self::assertSame('0.0000', $warehouseStock->getReservedQuantity());
	}

	private function createOrderEntryAndStock(string $quantityOnHand, string $reservedQuantity, string $entryQuantity, bool $allowBackorders = false): array
	{
		$currency = (new Currency())
			->setCode($this->uniqueCurrencyCode('R'))
			->setName('Reservation currency')
			->setSymbol('R')
			->setDecimalPlaces(2);
		$store = (new Store())
			->setName('Reservation store ' . uniqid())
			->setSlug('reservation-store-' . uniqid())
			->setPhone('+380000000000')
			->setEmail('reservation-' . uniqid() . '@example.com')
			->setBaseCurrency($currency)
			->setAllowBackorders($allowBackorders);
		$warehouse = (new Warehouse())
			->setName('Reservation warehouse ' . uniqid())
			->setStore($store);
		$category = (new Category())
			->setName('Reservation category ' . uniqid())
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
			->setName('Reservation product ' . uniqid())
			->setCode('reservation-product-' . uniqid())
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);
		$order = (new Order())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('reservation-order-' . uniqid());
		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity($entryQuantity)
			->setUnitPrice('10.0000')
			->setUnitPriceBase('10.0000')
			->setTotalPrice('10.0000')
			->setTotalPriceBase('10.0000')
			->setProductNameSnapshot($product->getName())
			->setProductCodeSnapshot($product->getCode())
			->setUnitCodeSnapshot($unit->getCode())
			->setUnitNameSnapshot($unit->getName());
		$order->addOrderEntry($orderEntry);
		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setQuantityOnHand($quantityOnHand)
			->setReservedQuantity($reservedQuantity);

		$this->entityManager->persist($currency);
		$this->entityManager->persist($store);
		$this->entityManager->persist($warehouse);
		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->persist($order);
		$this->entityManager->persist($orderEntry);
		$this->entityManager->persist($warehouseStock);
		$this->entityManager->flush();

		return [$orderEntry, $warehouseStock];
	}

	private function uniqueCurrencyCode(string $prefix): string
	{
		do {
			$code = $prefix . str_pad(strtoupper(base_convert((string) random_int(0, 1295), 10, 36)), 2, '0', STR_PAD_LEFT);
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
	}
}

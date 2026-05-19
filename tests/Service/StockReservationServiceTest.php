<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\StockReservationStatus;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Service\StockReservationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StockReservationServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private StockReservationService $stockReservationService;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->stockReservationService = static::getContainer()->get(StockReservationService::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->stockReservationService);
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

<?php

namespace App\Tests\Service\Dashboard;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use App\Enum\ProductKindEnum;
use App\Service\Dashboard\StoreDashboardProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StoreDashboardProviderTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private StoreDashboardProvider $dashboardProvider;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->dashboardProvider = static::getContainer()->get(StoreDashboardProvider::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->dashboardProvider);
	}

	public function testBuildIncludesFinancialAndOperationalStoreMetrics(): void
	{
		$currency = $this->persistCurrency('D' . substr(uniqid(), -2));
		$store = $this->persistStore('dashboard-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$this->persistWarehouseStock($warehouse, $product, '5.0000', '2.0000', '7.0000');
		$order = $this->persistOrder($store, $product, '100.0000', '40.0000', new DateTimeImmutable('2026-05-10 10:00:00'));
		$this->persistPurchase($store, '50.0000', '20.0000', new DateTimeImmutable('2026-05-11 10:00:00'));
		$this->persistPayment($store, PaymentDirectionEnum::INCOMING, '40.0000', new DateTimeImmutable('2026-05-12 10:00:00'), $order);
		$this->persistPayment($store, PaymentDirectionEnum::OUTGOING, '10.0000', new DateTimeImmutable('2026-05-12 11:00:00'));
		$this->persistDraftInventoryDocument($store);

		$dashboard = $this->dashboardProvider->build(
			$store,
			new DateTimeImmutable('2026-05-01'),
			new DateTimeImmutable('2026-05-31'),
			$warehouse->getId(),
			true,
		);

		self::assertTrue($dashboard['canViewFinancial']);
		self::assertSame(1, $dashboard['overview']['ordersCount']);
		self::assertSame(100.0, $dashboard['overview']['salesTotal']);
		self::assertSame(40.0, $dashboard['overview']['incomingPayments']);
		self::assertSame(10.0, $dashboard['overview']['outgoingPayments']);
		self::assertSame(30.0, $dashboard['overview']['netCashFlow']);
		self::assertSame(60.0, $dashboard['overview']['unpaidOrdersTotal']);
		self::assertSame(30.0, $dashboard['overview']['unpaidPurchasesTotal']);
		self::assertSame(1, $dashboard['overview']['draftInventoryDocuments']);
		self::assertSame(35.0, $dashboard['stock']['stockValue']);
		self::assertSame(2.0, $dashboard['stock']['reservedQuantity']);
		self::assertNotEmpty($dashboard['sales']['topProducts']);
		self::assertNotEmpty($dashboard['payments']['recent']);
	}

	public function testBuildHidesFinancialMetricsForLimitedStoreManagerView(): void
	{
		$currency = $this->persistCurrency('M' . substr(uniqid(), -2));
		$store = $this->persistStore('dashboard-limited-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$this->persistWarehouseStock($warehouse, $product, '1.0000', '1.0000', '9.0000');
		$this->persistOrder($store, $product, '80.0000', '0.0000', new DateTimeImmutable('2026-05-10 10:00:00'));

		$dashboard = $this->dashboardProvider->build(
			$store,
			new DateTimeImmutable('2026-05-01'),
			new DateTimeImmutable('2026-05-31'),
			null,
			false,
		);

		self::assertFalse($dashboard['canViewFinancial']);
		self::assertSame(1, $dashboard['overview']['ordersCount']);
		self::assertArrayNotHasKey('salesTotal', $dashboard['overview']);
		self::assertNull($dashboard['stock']['stockValue']);
		self::assertSame([], $dashboard['sales']['topProducts']);
		self::assertSame([], $dashboard['payments']['recent']);
		self::assertNull($dashboard['criticalStock'][0]['averageCost']);
	}

	private function persistCurrency(string $code): Currency
	{
		$existing = $this->entityManager->getRepository(Currency::class)->find($code);
		if ($existing instanceof Currency) {
			return $existing;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName('Currency ' . $code)
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
			->setName('Dashboard category ' . uniqid())
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
			->setName('Dashboard product ' . uniqid())
			->setCode('dashboard-product-' . uniqid())
			->setBaseSalePrice('10.0000')
			->setCanBeSold(true)
			->setCanBePurchased(true)
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
			->setName('Dashboard warehouse ' . uniqid())
			->setStore($store);

		$this->entityManager->persist($warehouse);
		$this->entityManager->flush();

		return $warehouse;
	}

	private function persistWarehouseStock(Warehouse $warehouse, Product $product, string $onHand, string $reserved, string $averageCost): WarehouseStock
	{
		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setQuantityOnHand($onHand)
			->setReservedQuantity($reserved)
			->setAverageCost($averageCost);

		$this->entityManager->persist($warehouseStock);
		$this->entityManager->flush();

		return $warehouseStock;
	}

	private function persistOrder(Store $store, Product $product, string $total, string $paid, DateTimeImmutable $createdAt): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-' . uniqid())
			->setStatus(OrderStatus::READY_TO_SHIP)
			->setTotalAmount($total)
			->setTotalAmountBase($total)
			->setPaidAmountBase($paid)
			->setCreatedAt($createdAt)
			->setUpdatedAt($createdAt);
		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('2.0000')
			->setUnitPrice('50.0000')
			->setUnitPriceBase('50.0000')
			->setTotalPrice($total)
			->setTotalPriceBase($total)
			->setProductNameSnapshot($product->getName())
			->setProductCodeSnapshot($product->getCode())
			->setUnitCodeSnapshot($product->getUnit()?->getCode() ?? 'pc')
			->setUnitNameSnapshot($product->getUnit()?->getName() ?? 'Piece');
		$order->addOrderEntry($orderEntry);

		$this->entityManager->persist($order);
		$this->entityManager->persist($orderEntry);
		$this->entityManager->flush();

		return $order;
	}

	private function persistPurchase(Store $store, string $total, string $paid, DateTimeImmutable $createdAt): Purchase
	{
		$purchase = (new Purchase())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('PO-' . uniqid())
			->setTotalAmount($total)
			->setTotalAmountBase($total)
			->setPaidAmountBase($paid)
			->setCreatedAt($createdAt)
			->setUpdatedAt($createdAt);

		$this->entityManager->persist($purchase);
		$this->entityManager->flush();

		return $purchase;
	}

	private function persistPayment(Store $store, PaymentDirectionEnum $direction, string $amount, DateTimeImmutable $paidAt, ?Order $order = null): Payment
	{
		$payment = (new Payment())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setDirection($direction)
			->setType(PaymentTypeEnum::CASH)
			->setAmount($amount)
			->setAmountBase($amount)
			->setPaidAt($paidAt)
			->setOrder($order)
			->setCreatedAt($paidAt)
			->setUpdatedAt($paidAt);

		$this->entityManager->persist($payment);
		$this->entityManager->flush();

		return $payment;
	}

	private function persistDraftInventoryDocument(Store $store): InventoryDocument
	{
		$document = (new InventoryDocument())
			->setStore($store)
			->setNumber('ID-' . uniqid())
			->setDocumentDate(new DateTimeImmutable('2026-05-15 10:00:00'));

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}
}

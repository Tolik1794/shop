<?php

namespace App\Tests\Service\Order;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\ProductKindEnum;
use App\Manager\OrderManager;
use App\Service\Order\AwaitingStockReplenishmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AwaitingStockReplenishmentServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;
	private AwaitingStockReplenishmentService $replenishmentService;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
		$this->replenishmentService = static::getContainer()->get(AwaitingStockReplenishmentService::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager, $this->replenishmentService);
	}

	public function testRefreshAvailabilityMovesAwaitingOrderToReadyWhenStockArrives(): void
	{
		[$store, $warehouse, $product, $warehouseStock] = $this->awaitingScenario('1.0000');
		$order = $this->confirmedAwaitingOrder($store, $warehouse, $product);

		self::assertSame(OrderStatus::AWAITING_STOCK, $order->getStatus());

		// Stock arrives.
		$warehouseStock->setQuantityOnHand('5.0000');
		$this->entityManager->flush();

		$this->orderManager->refreshAvailability($order);
		$this->entityManager->refresh($warehouseStock);

		self::assertSame(OrderStatus::READY_TO_SHIP, $order->getStatus());
		self::assertSame('2.0000', $warehouseStock->getReservedQuantity());
	}

	public function testRefreshAvailabilityKeepsAwaitingWhenStockStillMissing(): void
	{
		[$store, $warehouse, $product] = $this->awaitingScenario('0.0000');
		$order = $this->confirmedAwaitingOrder($store, $warehouse, $product);

		self::assertSame(OrderStatus::AWAITING_STOCK, $order->getStatus());

		$this->orderManager->refreshAvailability($order);

		self::assertSame(OrderStatus::AWAITING_STOCK, $order->getStatus());
	}

	public function testReplenishServiceAdvancesAwaitingOrderForArrivedProduct(): void
	{
		[$store, $warehouse, $product, $warehouseStock] = $this->awaitingScenario('1.0000');
		$order = $this->confirmedAwaitingOrder($store, $warehouse, $product);

		self::assertSame(OrderStatus::AWAITING_STOCK, $order->getStatus());

		$warehouseStock->setQuantityOnHand('5.0000');
		$this->entityManager->flush();

		$this->replenishmentService->replenish($store->getId(), [$product->getId()]);
		$this->entityManager->refresh($order);

		self::assertSame(OrderStatus::READY_TO_SHIP, $order->getStatus());
	}

	/**
	 * @return array{Store, Warehouse, Product, WarehouseStock}
	 */
	private function awaitingScenario(string $quantityOnHand): array
	{
		$currency = $this->persistCurrency('R' . substr(uniqid(), -2));
		$store = $this->persistStore('order-replenish-' . uniqid(), $currency)
			->setAllowBackorders(true);
		$product = $this->persistProduct($store);
		$warehouse = $this->persistWarehouse($store);
		$warehouseStock = $this->persistWarehouseStock($warehouse, $product, $quantityOnHand);

		return [$store, $warehouse, $product, $warehouseStock];
	}

	private function confirmedAwaitingOrder(Store $store, Warehouse $warehouse, Product $product): \App\Entity\Order
	{
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

		return $order;
	}

	private function persistCurrency(string $code): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($code)
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
}

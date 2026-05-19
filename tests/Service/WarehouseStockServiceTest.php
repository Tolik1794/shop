<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Repository\WarehouseStockRepository;
use App\Service\WarehouseStockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WarehouseStockServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private WarehouseStockService $warehouseStockService;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->warehouseStockService = new WarehouseStockService(
			$this->entityManager,
			static::getContainer()->get(WarehouseStockRepository::class)
		);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->warehouseStockService);
	}

	public function testFindOrCreateReturnsSameStockRowForProductAndWarehouse(): void
	{
		[$warehouse, $product] = $this->createWarehouseAndProduct();

		$firstWarehouseStock = $this->warehouseStockService->findOrCreate($warehouse, $product);
		$this->entityManager->flush();
		$secondWarehouseStock = $this->warehouseStockService->findOrCreate($warehouse, $product);

		self::assertSame($firstWarehouseStock->getId(), $secondWarehouseStock->getId());
	}

	public function testIncreaseReserveReleaseAndDecrease(): void
	{
		[$warehouse, $product] = $this->createWarehouseAndProduct();

		$warehouseStock = $this->warehouseStockService->increase($warehouse, $product, '10.0000', '2.5000');
		$this->warehouseStockService->reserve($warehouse, $product, '4.0000');
		$this->warehouseStockService->release($warehouse, $product, '1.5000');
		$this->warehouseStockService->decrease($warehouse, $product, '3.0000');
		$this->entityManager->refresh($warehouseStock);

		self::assertSame('7.0000', $warehouseStock->getQuantityOnHand());
		self::assertSame('2.5000', $warehouseStock->getReservedQuantity());
		self::assertSame('2.5000', $warehouseStock->getAverageCost());
	}

	public function testReserveMoreThanAvailableIsBlockedWhenBackordersDisabled(): void
	{
		[$warehouse, $product] = $this->createWarehouseAndProduct();
		$this->warehouseStockService->increase($warehouse, $product, '2.0000');

		$this->expectException(StockOperationException::class);

		$this->warehouseStockService->reserve($warehouse, $product, '3.0000');
	}

	public function testReserveMoreThanAvailableIsBlockedWhenBackordersEnabled(): void
	{
		[$warehouse, $product] = $this->createWarehouseAndProduct(true);
		$this->warehouseStockService->increase($warehouse, $product, '2.0000');

		$this->expectException(StockOperationException::class);

		$this->warehouseStockService->reserve($warehouse, $product, '3.0000');
	}

	public function testDecreaseBelowReservedQuantityIsBlockedWhenBackordersDisabled(): void
	{
		[$warehouse, $product] = $this->createWarehouseAndProduct();
		$this->warehouseStockService->increase($warehouse, $product, '5.0000');
		$this->warehouseStockService->reserve($warehouse, $product, '4.0000');

		$this->expectException(StockOperationException::class);

		$this->warehouseStockService->decrease($warehouse, $product, '2.0000');
	}

	private function createWarehouseAndProduct(bool $allowBackorders = false): array
	{
		$currencyCode = $this->uniqueCurrencyCode('W');
		$currency = (new Currency())
			->setCode($currencyCode)
			->setName('Warehouse currency')
			->setSymbol($currencyCode)
			->setDecimalPlaces(2);
		$store = (new Store())
			->setName('Warehouse stock store ' . uniqid())
			->setSlug('warehouse-stock-store-' . uniqid())
			->setPhone('+380000000000')
			->setEmail('warehouse-stock-' . uniqid() . '@example.com')
			->setBaseCurrency($currency)
			->setAllowBackorders($allowBackorders);
		$warehouse = (new Warehouse())
			->setName('Warehouse ' . uniqid())
			->setStore($store);
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
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($currency);
		$this->entityManager->persist($store);
		$this->entityManager->persist($warehouse);
		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return [$warehouse, $product];
	}

	private function uniqueCurrencyCode(string $prefix): string
	{
		do {
			$code = $prefix . str_pad(strtoupper(base_convert((string) random_int(0, 1295), 10, 36)), 2, '0', STR_PAD_LEFT);
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
	}
}

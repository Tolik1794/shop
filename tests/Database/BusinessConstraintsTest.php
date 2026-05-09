<?php

namespace App\Tests\Database;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BusinessConstraintsTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager);
	}

	public function testStoreSlugMustBeUnique(): void
	{
		$slug = 'store-' . uniqid();
		$this->persistStore($slug);
		$this->persistStore($slug);

		$this->expectException(Exception::class);
		$this->entityManager->flush();
	}

	public function testProductCodeMustBeUniqueWithinStore(): void
	{
		$store = $this->persistStore('product-store-' . uniqid());
		$category = $this->persistCategory($store, 'Products');
		$this->persistProduct($store, $category, 'SKU-1');
		$this->persistProduct($store, $category, 'SKU-1');

		$this->expectException(Exception::class);
		$this->entityManager->flush();
	}

	public function testWarehouseNameMustBeUniqueWithinStore(): void
	{
		$store = $this->persistStore('warehouse-store-' . uniqid());
		$this->persistWarehouse($store, 'Main');
		$this->persistWarehouse($store, 'Main');

		$this->expectException(Exception::class);
		$this->entityManager->flush();
	}

	public function testWarehouseStockQuantityCannotBeNegative(): void
	{
		$store = $this->persistStore('stock-store-' . uniqid());
		$category = $this->persistCategory($store, 'Stock');
		$product = $this->persistProduct($store, $category, 'SKU-' . uniqid());
		$warehouse = $this->persistWarehouse($store, 'Main');

		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setAverageCost('10.0000')
			->setQuantityOnHand('-1.0000')
			->setReservedQuantity('0.0000');

		$this->entityManager->persist($warehouseStock);

		$this->expectException(Exception::class);
		$this->entityManager->flush();
	}

	private function persistStore(string $slug): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->persistCurrency());

		$this->entityManager->persist($store);

		return $store;
	}

	private function persistCurrency(): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find('UAH');

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode('UAH')
			->setName('Ukrainian hryvnia')
			->setSymbol('UAH')
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);

		return $currency;
	}

	private function persistCategory(Store $store, string $name): Category
	{
		$category = (new Category())
			->setStore($store)
			->setName($name)
			->setLevel(1);

		$this->entityManager->persist($category);

		return $category;
	}

	private function persistProduct(Store $store, Category $category, string $code): Product
	{
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($this->persistUnit($store, 'pcs-' . uniqid()))
			->setName($code)
			->setCode($code)
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($product);

		return $product;
	}

	private function persistUnit(Store $store, string $code): Unit
	{
		$unit = (new Unit())
			->setStore($store)
			->setCode($code)
			->setName($code)
			->setPrecision(0);

		$this->entityManager->persist($unit);

		return $unit;
	}

	private function persistWarehouse(Store $store, string $name): Warehouse
	{
		$warehouse = (new Warehouse())
			->setStore($store)
			->setName($name);

		$this->entityManager->persist($warehouse);

		return $warehouse;
	}
}

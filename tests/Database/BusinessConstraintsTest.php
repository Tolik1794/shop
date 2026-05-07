<?php

namespace App\Tests\Database;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Store;
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

	public function testWarehouseStockCountCannotBeNegative(): void
	{
		$store = $this->persistStore('stock-store-' . uniqid());
		$category = $this->persistCategory($store, 'Stock');
		$product = $this->persistProduct($store, $category, 'SKU-' . uniqid());
		$warehouse = $this->persistWarehouse($store, 'Main');

		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setPurchasePrice('10.0000')
			->setMinimumSellingPrice('11.0000')
			->setSellingPrice('12.0000')
			->setCount(-1)
			->setReserveCount(0);

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
			->setEmail($slug . '@example.com');

		$this->entityManager->persist($store);

		return $store;
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
			->setName($code)
			->setCode($code);

		$this->entityManager->persist($product);

		return $product;
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

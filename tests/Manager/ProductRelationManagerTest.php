<?php

namespace App\Tests\Manager;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductRelation;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductRelationTypeEnum;
use App\Manager\ProductRelationManager;
use App\Repository\ProductRelationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductRelationManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private ProductRelationManager $manager;
	private ProductRelationRepository $repository;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->manager = static::getContainer()->get(ProductRelationManager::class);
		$this->repository = static::getContainer()->get(ProductRelationRepository::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->manager, $this->repository);
	}

	public function testSyncCreatesMirrorRelationWithSameType(): void
	{
		$store = $this->persistStore();
		$table = $this->persistProduct($store, 'Table');
		$chair = $this->persistProduct($store, 'Chair');

		$this->addRelation($table, $chair, ProductRelationTypeEnum::ACCESSORY);
		$this->entityManager->flush();

		$this->manager->syncMirrors($table);

		$mirror = $this->repository->findOnePair($chair, $table);
		self::assertInstanceOf(ProductRelation::class, $mirror);
		self::assertSame(ProductRelationTypeEnum::ACCESSORY, $mirror->getType());
	}

	public function testSyncIsIdempotent(): void
	{
		$store = $this->persistStore();
		$table = $this->persistProduct($store, 'Table');
		$chair = $this->persistProduct($store, 'Chair');

		$this->addRelation($table, $chair, ProductRelationTypeEnum::ACCESSORY);
		$this->entityManager->flush();

		$this->manager->syncMirrors($table);
		$this->manager->syncMirrors($table);

		self::assertCount(1, $this->repository->findBy(['product' => $chair, 'relatedProduct' => $table]));
	}

	public function testRemovingDirectRelationRemovesMirror(): void
	{
		$store = $this->persistStore();
		$table = $this->persistProduct($store, 'Table');
		$chair = $this->persistProduct($store, 'Chair');

		$relation = $this->addRelation($table, $chair, ProductRelationTypeEnum::ACCESSORY);
		$this->entityManager->flush();
		$this->manager->syncMirrors($table);

		// Simulate the edit form removing the direct relation (orphanRemoval).
		$table->removeProductRelation($relation);
		$this->entityManager->flush();
		$this->manager->syncMirrors($table);

		self::assertNull($this->repository->findOnePair($chair, $table));
		self::assertNull($this->repository->findOnePair($table, $chair));
	}

	public function testSelfRelationIsSkipped(): void
	{
		$store = $this->persistStore();
		$table = $this->persistProduct($store, 'Table');

		$this->addRelation($table, $table, ProductRelationTypeEnum::RELATED);
		$this->entityManager->flush();

		$this->manager->syncMirrors($table);

		// No additional mirror beyond the (skipped) self row.
		self::assertCount(1, $this->repository->findBy(['relatedProduct' => $table]));
	}

	private function addRelation(Product $product, Product $relatedProduct, ProductRelationTypeEnum $type): ProductRelation
	{
		$relation = (new ProductRelation())
			->setRelatedProduct($relatedProduct)
			->setType($type);
		$product->addProductRelation($relation);
		$this->entityManager->persist($relation);

		return $relation;
	}

	private function persistStore(): Store
	{
		$slug = 'relation-store-' . uniqid();
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->persistCurrency());

		$this->entityManager->persist($store);
		$this->entityManager->flush();

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
		$this->entityManager->flush();

		return $currency;
	}

	private function persistProduct(Store $store, string $name): Product
	{
		$product = (new Product())
			->setStore($store)
			->setCategory($this->persistCategory($store, $name . '-cat'))
			->setUnit($this->persistUnit($store, 'u-' . uniqid()))
			->setName($name)
			->setCode($name . '-' . uniqid())
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}

	private function persistCategory(Store $store, string $name): Category
	{
		$category = (new Category())
			->setStore($store)
			->setName($name . '-' . uniqid())
			->setLevel(1);

		$this->entityManager->persist($category);
		$this->entityManager->flush();

		return $category;
	}

	private function persistUnit(Store $store, string $code): Unit
	{
		$unit = (new Unit())
			->setStore($store)
			->setCode($code)
			->setName($code)
			->setPrecision(0);

		$this->entityManager->persist($unit);
		$this->entityManager->flush();

		return $unit;
	}
}

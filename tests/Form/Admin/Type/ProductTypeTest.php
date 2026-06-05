<?php

namespace App\Tests\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductParameterName;
use App\Entity\Store;
use App\Entity\Unit;
use App\Form\Admin\Type\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class ProductTypeTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private FormFactoryInterface $formFactory;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->formFactory);
	}

	public function testNewProductFormCanBeCreatedWithoutSelectedCategory(): void
	{
		$product = (new Product())->setStore($this->persistStore('new-product-store-' . uniqid()));

		$form = $this->formFactory->create(ProductType::class, $product);

		self::assertTrue($form->has('category'));
		self::assertTrue($form->has('productParameters'));
		self::assertCount(0, $form->get('productParameters'));
	}

	public function testEditProductFormContainsParametersForExistingCategory(): void
	{
		$store = $this->persistStore('edit-product-store-' . uniqid());
		$category = $this->persistCategory($store, 'Shoes');
		$this->persistCategoryProductParameterName($category, 'Size');
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($this->persistUnit($store, 'pcs-' . uniqid()))
			->setName('Sneaker')
			->setCode('SN-' . uniqid());

		$form = $this->formFactory->create(ProductType::class, $product);

		self::assertTrue($form->has('productParameters'));
		self::assertTrue($form->get('productParameters')->has('Size'));
	}

	public function testProductFormSubmitsRelatedProductsCollection(): void
	{
		$store = $this->persistStore('relations-store-' . uniqid());
		$category = $this->persistCategory($store, 'Furniture');
		$unit = $this->persistUnit($store, 'pcs-' . uniqid());
		$table = $this->persistProduct($store, $category, $unit, 'Table');
		$chair = $this->persistProduct($store, $category, $unit, 'Chair');

		$form = $this->formFactory->create(ProductType::class, $table, ['csrf_protection' => false]);

		self::assertTrue($form->has('productRelations'));

		$form->submit([
			'name' => $table->getName(),
			'code' => $table->getCode(),
			'unit' => (string) $unit->getId(),
			'productKind' => $table->getProductKind()->value,
			'category' => (string) $category->getId(),
			'canBeSold' => '1',
			'productRelations' => [
				['relatedProduct' => (string) $chair->getId(), 'type' => 'accessory'],
			],
		], false);

		self::assertTrue($form->isValid(), (string) $form->getErrors(true));
		self::assertCount(1, $table->getProductRelations());

		$relation = $table->getProductRelations()->first();
		self::assertSame($chair->getId(), $relation->getRelatedProduct()->getId());
		self::assertSame('accessory', $relation->getType()->value);
		self::assertSame($table, $relation->getProduct());
	}

	public function testProductFormSubmitsProductParametersCollection(): void
	{
		$store = $this->persistStore('parameter-submit-store-' . uniqid());
		$category = $this->persistCategory($store, 'Shoes');
		$unit = $this->persistUnit($store, 'pcs-' . uniqid());
		$size = $this->persistCategoryProductParameterName($category, 'Size');
		$product = (new Product())
			->setStore($store)
			->setName('Sneaker')
			->setCode('SN-' . uniqid())
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$form = $this->formFactory->create(ProductType::class, $product, ['csrf_protection' => false]);
		$form->submit([
			'name' => $product->getName(),
			'code' => $product->getCode(),
			'unit' => (string) $unit->getId(),
			'productKind' => $product->getProductKind()->value,
			'category' => (string) $category->getId(),
			'canBeSold' => '1',
			'productParameters' => [
				['productParameterName' => (string) $size->getId(), 'value' => '42'],
			],
		]);

		self::assertTrue($form->isValid(), (string) $form->getErrors(true));
		self::assertCount(1, $product->getProductParameters());

		$parameter = $product->getProductParameters()->first();
		self::assertSame($size->getId(), $parameter->getProductParameterName()->getId());
		self::assertSame('42', $parameter->getValue());
		self::assertSame($product, $parameter->getProduct());
	}

	public function testProductFormRejectsDuplicateProductParameters(): void
	{
		$store = $this->persistStore('parameter-duplicate-store-' . uniqid());
		$category = $this->persistCategory($store, 'Shoes');
		$unit = $this->persistUnit($store, 'pcs-' . uniqid());
		$size = $this->persistCategoryProductParameterName($category, 'Size');
		$product = $this->persistProduct($store, $category, $unit, 'Sneaker');

		$form = $this->formFactory->create(ProductType::class, $product, ['csrf_protection' => false]);
		$form->submit([
			'name' => $product->getName(),
			'code' => $product->getCode(),
			'unit' => (string) $unit->getId(),
			'productKind' => $product->getProductKind()->value,
			'category' => (string) $category->getId(),
			'canBeSold' => '1',
			'productParameters' => [
				['productParameterName' => (string) $size->getId(), 'value' => '42'],
				['productParameterName' => (string) $size->getId(), 'value' => '43'],
			],
		]);

		self::assertFalse($form->isValid());
		self::assertStringContainsString('This product parameter is already added.', (string) $form->getErrors(true));
	}

	public function testProductFormRejectsParameterOutsideSelectedCategory(): void
	{
		$store = $this->persistStore('parameter-outside-store-' . uniqid());
		$category = $this->persistCategory($store, 'Shoes');
		$otherCategory = $this->persistCategory($store, 'Tables');
		$unit = $this->persistUnit($store, 'pcs-' . uniqid());
		$material = $this->persistCategoryProductParameterName($otherCategory, 'Material');
		$product = $this->persistProduct($store, $category, $unit, 'Sneaker');

		$form = $this->formFactory->create(ProductType::class, $product, ['csrf_protection' => false]);
		$form->submit([
			'name' => $product->getName(),
			'code' => $product->getCode(),
			'unit' => (string) $unit->getId(),
			'productKind' => $product->getProductKind()->value,
			'category' => (string) $category->getId(),
			'canBeSold' => '1',
			'productParameters' => [
				['productParameterName' => (string) $material->getId(), 'value' => 'Wood'],
			],
		]);

		self::assertFalse($form->isValid());
	}

	private function persistProduct(Store $store, Category $category, Unit $unit, string $name): Product
	{
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($unit)
			->setName($name)
			->setCode($name . '-' . uniqid())
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
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

	private function persistCategory(Store $store, string $name): Category
	{
		$category = (new Category())
			->setStore($store)
			->setName($name)
			->setLevel(1);

		$this->entityManager->persist($category);
		$this->entityManager->flush();

		return $category;
	}

	private function persistCategoryProductParameterName(Category $category, string $name): ProductParameterName
	{
		$productParameterName = new ProductParameterName()
			->setName($name)
			->setDescription($name);
		$categoryProductParameterName = new CategoryProductParameterName()
			->setCategory($category)
			->setProductParameterName($productParameterName)
			->setIsFilter(true)
			->setIsRequired(true);

		$this->entityManager->persist($productParameterName);
		$this->entityManager->persist($categoryProductParameterName);
		$this->entityManager->flush();

		return $productParameterName;
	}
}

<?php

namespace App\Tests\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Product;
use App\Entity\ProductParameterName;
use App\Entity\Store;
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
		self::assertFalse($form->has('productParameters'));
	}

	public function testEditProductFormContainsParametersForExistingCategory(): void
	{
		$store = $this->persistStore('edit-product-store-' . uniqid());
		$category = $this->persistCategory($store, 'Shoes');
		$this->persistCategoryProductParameterName($category, 'Size');
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setName('Sneaker')
			->setCode('SN-' . uniqid());

		$form = $this->formFactory->create(ProductType::class, $product);

		self::assertTrue($form->has('productParameters'));
		self::assertTrue($form->get('productParameters')->has('Size'));
	}

	private function persistStore(string $slug): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com');

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
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

	private function persistCategoryProductParameterName(Category $category, string $name): void
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
	}
}

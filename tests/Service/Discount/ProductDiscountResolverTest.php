<?php

namespace App\Tests\Service\Discount;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\ProductDiscountTarget;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductDiscountTargetTypeEnum;
use App\Enum\ProductKindEnum;
use App\Service\Discount\ProductDiscountResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductDiscountResolverTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private ProductDiscountResolver $resolver;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->resolver = static::getContainer()->get(ProductDiscountResolver::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->resolver);
	}

	public function testDefaultRulePrefersProductTargetOverCategoryTarget(): void
	{
		$currency = $this->persistCurrency('R' . substr(uniqid(), -2));
		$store = $this->persistStore('discount-resolver-' . uniqid(), $currency);
		$category = $this->persistCategory($store, 'Category ' . uniqid());
		$product = $this->persistProduct($store, $category);
		$this->persistDiscountRule($store, 'Category discount', '25.0000', true, ProductDiscountTargetTypeEnum::CATEGORY, category: $category);
		$productRule = $this->persistDiscountRule($store, 'Product discount', '10.0000', true, ProductDiscountTargetTypeEnum::PRODUCT, product: $product);

		self::assertSame($productRule->getId(), $this->resolver->defaultRule($product, $store)?->getId());
	}

	public function testDefaultRulePrefersCloserCategoryThenHigherPercent(): void
	{
		$currency = $this->persistCurrency('S' . substr(uniqid(), -2));
		$store = $this->persistStore('discount-resolver-category-' . uniqid(), $currency);
		$parent = $this->persistCategory($store, 'Parent ' . uniqid());
		$child = $this->persistCategory($store, 'Child ' . uniqid(), $parent);
		$product = $this->persistProduct($store, $child);
		$this->persistDiscountRule($store, 'Parent discount', '30.0000', true, ProductDiscountTargetTypeEnum::CATEGORY, category: $parent);
		$childRule = $this->persistDiscountRule($store, 'Child discount', '12.0000', true, ProductDiscountTargetTypeEnum::CATEGORY, category: $child);
		$this->persistDiscountRule($store, 'Child discount high', '14.0000', true, ProductDiscountTargetTypeEnum::CATEGORY, category: $child);

		self::assertSame('Child discount high', $this->resolver->defaultRule($product, $store)?->getName());
		self::assertNotSame($childRule->getId(), $this->resolver->defaultRule($product, $store)?->getId());
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

	private function persistCategory(Store $store, string $name, ?Category $parent = null): Category
	{
		$category = (new Category())
			->setName($name)
			->setLevel($parent instanceof Category ? $parent->getLevel() + 1 : 1)
			->setParent($parent)
			->setStore($store);

		$this->entityManager->persist($category);
		$this->entityManager->flush();

		return $category;
	}

	private function persistProduct(Store $store, Category $category): Product
	{
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

		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}

	private function persistDiscountRule(
		Store $store,
		string $name,
		string $percent,
		bool $isDefault,
		ProductDiscountTargetTypeEnum $targetType,
		?Product $product = null,
		?Category $category = null,
	): ProductDiscountRule {
		$rule = (new ProductDiscountRule())
			->setStore($store)
			->setName($name)
			->setPercent($percent)
			->setIsDefault($isDefault);
		$rule->addTarget((new ProductDiscountTarget())
			->setTargetType($targetType)
			->setProduct($product)
			->setCategory($category));

		$this->entityManager->persist($rule);
		$this->entityManager->flush();

		return $rule;
	}
}

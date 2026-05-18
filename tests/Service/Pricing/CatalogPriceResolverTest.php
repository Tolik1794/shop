<?php

namespace App\Tests\Service\Pricing;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use App\Enum\ProductPriceTypeEnum;
use App\Service\Pricing\CatalogPriceResolver;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CatalogPriceResolverTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private CatalogPriceResolver $catalogPriceResolver;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->catalogPriceResolver = static::getContainer()->get(CatalogPriceResolver::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->catalogPriceResolver);
	}

	public function testCurrentRegularPriceHasPriorityOverBaseSalePriceFallback(): void
	{
		$currency = $this->persistCurrency('P' . substr(uniqid(), -2), 'Price currency');
		$store = $this->persistStore('catalog-price-current-' . uniqid(), $currency);
		$product = $this->persistProduct($store, '99.0000');
		$this->persistProductPrice($product, $store, $currency, '15.2500');

		$resolvedPrice = $this->catalogPriceResolver->resolve($product, $store, $currency);

		self::assertSame('15.2500', $resolvedPrice->getAmount());
		self::assertSame('product_price', $resolvedPrice->getSource());
	}

	public function testBaseSalePriceFallbackIsConvertedToRequestedCurrency(): void
	{
		$baseCurrency = $this->persistCurrency('B' . substr(uniqid(), -2), 'Base currency');
		$orderCurrency = $this->persistCurrency('D' . substr(uniqid(), -2), 'Document currency');
		$store = $this->persistStore('catalog-price-fallback-' . uniqid(), $baseCurrency);
		$product = $this->persistProduct($store, '410.0000');
		$this->persistExchangeRate($orderCurrency, $baseCurrency, $store, '41.00000000');

		$resolvedPrice = $this->catalogPriceResolver->resolve($product, $store, $orderCurrency);

		self::assertSame('10.0000', $resolvedPrice->getAmount());
		self::assertSame('base_sale_price', $resolvedPrice->getSource());
	}

	private function persistCurrency(string $code, string $name): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($name)
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

	private function persistProduct(Store $store, string $baseSalePrice): Product
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
			->setBaseSalePrice($baseSalePrice)
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}

	private function persistProductPrice(Product $product, Store $store, Currency $currency, string $price): ProductPrice
	{
		$productPrice = (new ProductPrice())
			->setProduct($product)
			->setStore($store)
			->setCurrency($currency)
			->setType(ProductPriceTypeEnum::REGULAR)
			->setPrice($price)
			->setPriceBase($price)
			->setValidFrom(new DateTimeImmutable('2026-01-01 00:00:00'));

		$this->entityManager->persist($productPrice);
		$this->entityManager->flush();

		return $productPrice;
	}

	private function persistExchangeRate(Currency $fromCurrency, Currency $toCurrency, Store $store, string $rate): ExchangeRate
	{
		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate($rate)
			->setValidFrom(new DateTimeImmutable('2026-01-01 00:00:00'));

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();

		return $exchangeRate;
	}
}

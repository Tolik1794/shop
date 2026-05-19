<?php

namespace App\Tests\Manager;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use App\Enum\ProductPriceTypeEnum;
use App\Manager\ProductPriceManager;
use App\Repository\ProductPriceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductPriceManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private ProductPriceManager $productPriceManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->productPriceManager = static::getContainer()->get(ProductPriceManager::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->productPriceManager);
	}

	public function testSaveWithTimelineCalculatesBasePriceAndClosesPreviousOpenPrice(): void
	{
		$baseCurrency = $this->persistCurrency('P' . substr(uniqid(), -2), 'Base currency');
		$priceCurrency = $this->persistCurrency('Q' . substr(uniqid(), -2), 'Price currency');
		$store = $this->persistStore('product-price-' . uniqid(), $baseCurrency);
		$product = $this->persistProduct($store);
		$validFrom = new DateTimeImmutable('2026-02-01 00:00:00');

		$this->persistExchangeRate($priceCurrency, $baseCurrency, $store, '2.00000000', new DateTimeImmutable('2026-01-01 00:00:00'));
		$previousProductPrice = $this->persistProductPrice($product, $store, $priceCurrency, '10.0000', new DateTimeImmutable('2026-01-01 00:00:00'));

		$productPrice = (new ProductPrice())
			->setProduct($product)
			->setStore($store)
			->setCurrency($priceCurrency)
			->setType(ProductPriceTypeEnum::REGULAR)
			->setPrice('12.5000')
			->setValidFrom($validFrom);

		$this->productPriceManager->saveWithTimeline($productPrice);
		$this->entityManager->refresh($previousProductPrice);
		$this->entityManager->refresh($product);

		self::assertSame('25.0000', $productPrice->getPriceBase());
		self::assertSame('25.0000', $product->getBaseSalePrice());
		self::assertEquals($validFrom, $previousProductPrice->getValidTo());
	}

	public function testNewPriceInsideClosedIntervalIsBlocked(): void
	{
		$currency = $this->persistCurrency('R' . substr(uniqid(), -2), 'Currency');
		$store = $this->persistStore('product-price-block-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$repository = static::getContainer()->get(ProductPriceRepository::class);

		$this->persistProductPrice(
			$product,
			$store,
			$currency,
			'10.0000',
			new DateTimeImmutable('2026-01-01 00:00:00'),
			new DateTimeImmutable('2026-02-01 00:00:00')
		);

		$productPrice = (new ProductPrice())
			->setProduct($product)
			->setStore($store)
			->setCurrency($currency)
			->setType(ProductPriceTypeEnum::REGULAR)
			->setPrice('12.5000')
			->setValidFrom(new DateTimeImmutable('2026-01-15 00:00:00'));

		self::assertTrue($repository->hasBlockingPriceForNewStart($productPrice));
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
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}

	private function persistExchangeRate(
		Currency $fromCurrency,
		Currency $toCurrency,
		?Store $store,
		string $rate,
		DateTimeImmutable $validFrom,
	): ExchangeRate {
		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate($rate)
			->setValidFrom($validFrom);

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();

		return $exchangeRate;
	}

	private function persistProductPrice(
		Product $product,
		Store $store,
		Currency $currency,
		string $price,
		DateTimeImmutable $validFrom,
		?DateTimeImmutable $validTo = null,
	): ProductPrice {
		$productPrice = (new ProductPrice())
			->setProduct($product)
			->setStore($store)
			->setCurrency($currency)
			->setType(ProductPriceTypeEnum::REGULAR)
			->setPrice($price)
			->setPriceBase($price)
			->setValidFrom($validFrom)
			->setValidTo($validTo);

		$this->entityManager->persist($productPrice);
		$this->entityManager->flush();

		return $productPrice;
	}
}

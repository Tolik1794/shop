<?php

namespace App\Tests\Manager;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Product;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Entity\Supplier;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Enum\ProductKindEnum;
use App\Manager\PurchaseManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PurchaseManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private PurchaseManager $purchaseManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->purchaseManager = static::getContainer()->get(PurchaseManager::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->purchaseManager);
	}

	public function testSavePurchaseCopiesSnapshotsConvertsCostsAndAllocatesDeliveryByLineValue(): void
	{
		$baseCurrency = $this->persistCurrency('B' . substr(uniqid(), -2), 'Base currency');
		$purchaseCurrency = $this->persistCurrency('P' . substr(uniqid(), -2), 'Purchase currency');
		$store = $this->persistStore('purchase-' . uniqid(), $baseCurrency);
		$supplier = $this->persistSupplier($store);
		$warehouse = $this->persistWarehouse($store);
		$firstProduct = $this->persistProduct($store);
		$secondProduct = $this->persistProduct($store);
		$this->persistExchangeRate($purchaseCurrency, $baseCurrency, $store, '2.00000000');

		$purchase = $this->purchaseManager->createDraft($store)
			->setSupplier($supplier)
			->setCurrency($purchaseCurrency)
			->setDeliveryCost('30.0000');
		$purchase->addPurchaseEntry((new PurchaseEntry())
			->setProduct($firstProduct)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitCost('10.0000'));
		$purchase->addPurchaseEntry((new PurchaseEntry())
			->setProduct($secondProduct)
			->setWarehouse($warehouse)
			->setQuantity('1.0000')
			->setUnitCost('40.0000'));

		$this->purchaseManager->savePurchase($purchase);

		$entries = $purchase->getPurchaseEntries()->toArray();

		self::assertSame('PO-' . $store->getId() . '-000001', $purchase->getNumber());
		self::assertSame('2.00000000', $purchase->getExchangeRateToBase());
		self::assertSame($supplier->getName(), $purchase->getSupplierNameSnapshot());
		self::assertSame('90.0000', $purchase->getTotalAmount());
		self::assertSame('180.0000', $purchase->getTotalAmountBase());
		self::assertSame('30.0000', $purchase->getDeliveryCost());
		self::assertSame('60.0000', $purchase->getDeliveryCostBase());
		self::assertSame('10.0000', $entries[0]->getDeliveryCost());
		self::assertSame('30.0000', $entries[0]->getTotalCost());
		self::assertSame('20.0000', $entries[0]->getUnitCostBase());
		self::assertSame($firstProduct->getName(), $entries[0]->getProductNameSnapshot());
		self::assertSame('20.0000', $entries[1]->getDeliveryCost());
		self::assertSame('60.0000', $entries[1]->getTotalCost());
	}

	public function testPurchaseNumberIsGeneratedPerStore(): void
	{
		$currency = $this->persistCurrency('N' . substr(uniqid(), -2), 'Number currency');
		$firstStore = $this->persistStore('purchase-number-a-' . uniqid(), $currency);
		$secondStore = $this->persistStore('purchase-number-b-' . uniqid(), $currency);

		$firstPurchase = $this->purchaseManager->createDraft($firstStore);
		$this->purchaseManager->savePurchase($firstPurchase);
		$secondPurchase = $this->purchaseManager->createDraft($firstStore);
		$this->purchaseManager->savePurchase($secondPurchase);
		$otherStorePurchase = $this->purchaseManager->createDraft($secondStore);
		$this->purchaseManager->savePurchase($otherStorePurchase);

		self::assertSame('PO-' . $firstStore->getId() . '-000001', $firstPurchase->getNumber());
		self::assertSame('PO-' . $firstStore->getId() . '-000002', $secondPurchase->getNumber());
		self::assertSame('PO-' . $secondStore->getId() . '-000001', $otherStorePurchase->getNumber());
	}

	public function testOrderAndCancelUseControlledTransitions(): void
	{
		$currency = $this->persistCurrency('T' . substr(uniqid(), -2), 'Transition currency');
		$store = $this->persistStore('purchase-transition-' . uniqid(), $currency);
		$warehouse = $this->persistWarehouse($store);
		$product = $this->persistProduct($store);
		$purchase = $this->purchaseManager->createDraft($store);
		$purchase->addPurchaseEntry((new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('1.0000')
			->setUnitCost('10.0000'));
		$this->purchaseManager->savePurchase($purchase);

		$this->purchaseManager->order($purchase);
		self::assertSame(PurchaseStatus::ORDERED, $purchase->getStatus());

		$this->purchaseManager->returnToDraft($purchase);
		self::assertSame(PurchaseStatus::DRAFT, $purchase->getStatus());

		$this->purchaseManager->cancel($purchase);
		self::assertSame(PurchaseStatus::CANCELED, $purchase->getStatus());
		self::assertNotNull($purchase->getCanceledAt());
	}

	public function testPurchaseCannotBeOrderedWithoutEntries(): void
	{
		$currency = $this->persistCurrency('E' . substr(uniqid(), -2), 'Empty currency');
		$store = $this->persistStore('purchase-empty-' . uniqid(), $currency);
		$purchase = $this->purchaseManager->createDraft($store);
		$this->purchaseManager->savePurchase($purchase);

		$this->expectException(RuntimeException::class);

		$this->purchaseManager->order($purchase);
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

	private function persistSupplier(Store $store): Supplier
	{
		$supplier = (new Supplier())
			->setName('Supplier ' . uniqid())
			->setPhone('+380' . random_int(100000000, 999999999))
			->setEmail('purchase-supplier-' . uniqid() . '@example.com')
			->setStore($store);

		$this->entityManager->persist($supplier);
		$this->entityManager->flush();

		return $supplier;
	}

	private function persistWarehouse(Store $store): Warehouse
	{
		$warehouse = (new Warehouse())
			->setName('Purchase warehouse ' . uniqid())
			->setStore($store);

		$this->entityManager->persist($warehouse);
		$this->entityManager->flush();

		return $warehouse;
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
			->setCode('purchase-product-' . uniqid())
			->setBaseSalePrice('10.0000')
			->setCanBeSold(true)
			->setCanBePurchased(true)
			->setCanBeManufactured(false);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
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

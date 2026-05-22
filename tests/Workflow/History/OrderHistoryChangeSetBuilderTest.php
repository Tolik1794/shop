<?php

namespace App\Tests\Workflow\History;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use App\Manager\OrderManager;
use App\Workflow\History\OrderHistoryChangeSetBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderHistoryChangeSetBuilderTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;
	private OrderHistoryChangeSetBuilder $changeSetBuilder;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
		$this->changeSetBuilder = static::getContainer()->get(OrderHistoryChangeSetBuilder::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager, $this->changeSetBuilder);
	}

	public function testBuildsOrderAndEntryChangesWithDecimalNormalization(): void
	{
		$currency = $this->persistCurrency('H' . substr(uniqid(), -2), 'History currency');
		$store = $this->persistStore('history-builder-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000')
			->setUnitPrice('99.0000')
			->setDiscountAmount('0.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);

		$order->setDeliveryAddress('Warehouse pickup');
		$orderEntry
			->setUnitPrice('99')
			->setDiscountAmount('0');

		self::assertSame([
			'deliveryAddress' => ['from' => null, 'to' => 'Warehouse pickup'],
		], $this->changeSetBuilder->buildOrderChanges($order));
		self::assertSame([], $this->changeSetBuilder->buildEntryChanges($orderEntry));

		$this->orderManager->saveOrder($order);
		$orderEntry->setUnitPrice('100');

		self::assertSame([
			'unitPrice' => ['from' => '99', 'to' => '100'],
		], $this->changeSetBuilder->buildEntryChanges($orderEntry));
	}

	private function persistCurrency(string $code, string $name): Currency
	{
		while ($this->entityManager->find(Currency::class, $code) instanceof Currency) {
			$code = 'H' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 2);
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
			->setBaseSalePrice('10.0000')
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured(false);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}
}

<?php

namespace App\Tests\Manager;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use App\Manager\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager);
	}

	public function testSaveOrderCopiesProductSnapshotAndRecalculatesTotals(): void
	{
		$currency = $this->persistCurrency('O' . substr(uniqid(), -2), 'Order currency');
		$store = $this->persistStore('order-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('2.0000')
			->setUnitPrice('15.0000')
			->setDiscountAmount('5.0000');
		$order->addOrderEntry($orderEntry);

		$this->orderManager->saveOrder($order);
		$this->entityManager->refresh($order);

		self::assertSame('25.0000', $order->getTotalAmount());
		self::assertSame('25.0000', $order->getTotalAmountBase());
		self::assertSame('5.0000', $order->getDiscountAmount());
		self::assertSame($product->getName(), $orderEntry->getProductNameSnapshot());
		self::assertSame($product->getUnit()?->getCode(), $orderEntry->getUnitCodeSnapshot());
	}

	public function testConfirmMovesDraftOrderToConfirmed(): void
	{
		$currency = $this->persistCurrency('S' . substr(uniqid(), -2), 'Sales currency');
		$store = $this->persistStore('order-confirm-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);

		self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());
	}

	public function testCancelMovesOrderToCanceledAndStoresTransitionTime(): void
	{
		$currency = $this->persistCurrency('C' . substr(uniqid(), -2), 'Cancel currency');
		$store = $this->persistStore('order-cancel-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$this->orderManager->cancel($order);

		self::assertSame(OrderStatus::CANCELED, $order->getStatus());
		self::assertNotNull($order->getCanceledAt());
	}

	public function testReturnToDraftMovesConfirmedOrderBackToDraft(): void
	{
		$currency = $this->persistCurrency('R' . substr(uniqid(), -2), 'Rollback currency');
		$store = $this->persistStore('order-return-to-draft-' . uniqid(), $currency);
		$product = $this->persistProduct($store);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setQuantity('1.0000')
			->setUnitPrice('10.0000');
		$order->addOrderEntry($orderEntry);
		$this->orderManager->saveOrder($order);
		$this->orderManager->confirm($order);
		$this->orderManager->returnToDraft($order);

		self::assertSame(OrderStatus::DRAFT, $order->getStatus());
	}

	public function testReturnToDraftDoesNotRestoreCanceledOrderYet(): void
	{
		$currency = $this->persistCurrency('X' . substr(uniqid(), -2), 'Canceled rollback currency');
		$store = $this->persistStore('order-canceled-rollback-' . uniqid(), $currency);
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$this->orderManager->cancel($order);

		$this->expectException(RuntimeException::class);

		$this->orderManager->returnToDraft($order);
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

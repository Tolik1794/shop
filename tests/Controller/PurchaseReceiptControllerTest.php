<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PurchaseReceiptControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testReceiveActionCreatesDraftPurchaseReceiptAndRedirectsToInventoryDocument(): void
	{
		$this->client->loginUser($this->createUser('purchase-receipt-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('purchase-receipt-' . uniqid());
		$purchase = $this->createPurchase($store, $warehouse, $product, PurchaseStatus::ORDERED, '3.0000', '0.0000');

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/purchase/%d/show',
			$store->getId(),
			$purchase->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertGreaterThan(0, $crawler->selectButton('Receive')->count());

		$this->client->submit($crawler->selectButton('Receive')->form());

		$document = $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'purchase' => $purchase,
			'type' => InventoryDocumentType::PURCHASE_RECEIPT,
		]);

		self::assertInstanceOf(InventoryDocument::class, $document);
		self::assertResponseRedirects(sprintf('/admin/store/%d/inventory-document/?id=%d', $store->getId(), $document->getId()));
		self::assertSame(InventoryDocumentStatus::DRAFT, $document->getStatus());
		self::assertSame($store->getId(), $document->getStore()?->getId());
		self::assertSame($purchase->getId(), $document->getPurchase()?->getId());
		self::assertSame($store->getBaseCurrency()?->getCode(), $document->getCurrency()?->getCode());
		self::assertSame('1.00000000', $document->getExchangeRateToBase());
		self::assertCount(1, $document->getLines());

		$line = $document->getLines()->first();

		self::assertSame(InventoryDirection::IN, $line->getDirection());
		self::assertSame('3.0000', $line->getQuantity());
		self::assertSame('4.0000', $line->getUnitPrice());
		self::assertSame('4.0000', $line->getUnitPriceBase());
		self::assertSame($purchase->getPurchaseEntries()->first()->getId(), $line->getPurchaseEntry()?->getId());

		$this->client->request('GET', sprintf(
			'/admin/store/%d/purchase/%d/show',
			$store->getId(),
			$purchase->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Inventory documents');
		self::assertSelectorTextContains('body', (string) $document->getNumber());

		$this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-document/%d/show',
			$store->getId(),
			$document->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Purchase ' . $purchase->getNumber());
		$this->client->submit($this->client->getCrawler()->selectButton('Post')->form());
		self::assertResponseRedirects(sprintf('/admin/store/%d/inventory-document/?id=%d', $store->getId(), $document->getId()));

		$this->entityManager->clear();
		$receivedPurchase = $this->entityManager->getRepository(Purchase::class)->find($purchase->getId());

		self::assertInstanceOf(Purchase::class, $receivedPurchase);
		self::assertSame(PurchaseStatus::RECEIVED, $receivedPurchase->getStatus());
		self::assertSame('3.0000', $receivedPurchase->getPurchaseEntries()->first()->getReceivedQuantity());

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/purchase/%d/show',
			$store->getId(),
			$purchase->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertGreaterThan(0, $crawler->selectButton('Complete')->count());

		$this->client->submit($crawler->selectButton('Complete')->form());
		self::assertResponseRedirects(sprintf('/admin/store/%d/purchase/?id=%d&page=1', $store->getId(), $purchase->getId()));

		$this->entityManager->clear();
		$receivedPurchase = $this->entityManager->getRepository(Purchase::class)->find($purchase->getId());

		self::assertInstanceOf(Purchase::class, $receivedPurchase);
		self::assertSame(PurchaseStatus::RECEIVED, $receivedPurchase->getStatus());
	}

	public function testReceiveActionRejectsInvalidCsrfToken(): void
	{
		$this->client->loginUser($this->createUser('purchase-receipt-csrf-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('purchase-receipt-csrf-' . uniqid());
		$purchase = $this->createPurchase($store, $warehouse, $product, PurchaseStatus::ORDERED, '1.0000', '0.0000');

		$this->client->request('POST', sprintf(
			'/admin/store/%d/purchase/%d/receive',
			$store->getId(),
			$purchase->getId(),
		), [
			'_token' => 'invalid',
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/purchase/?id=%d&page=1', $store->getId(), $purchase->getId()));
		self::assertNull($this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'purchase' => $purchase,
			'type' => InventoryDocumentType::PURCHASE_RECEIPT,
		]));
	}

	private function createUser(string $email): User
	{
		$user = (new User())
			->setEmail($email)
			->setNickname(str_replace(['@', '.'], '-', $email))
			->setFirstName('Admin')
			->setLastName('User')
			->setDateOfBirth(new DateTime('1990-01-01'))
			->setPassword('password')
			->setRoles([RoleEnum::ROLE_SUPER_ADMIN->name]);

		$this->entityManager->persist($user);
		$this->entityManager->flush();

		return $user;
	}

	/**
	 * @return array{Store, Warehouse, Product}
	 */
	private function createStoreWarehouseAndProduct(string $slug): array
	{
		$currency = $this->createCurrency();
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($currency);
		$category = (new Category())
			->setStore($store)
			->setName('Category ' . $slug)
			->setLevel(1);
		$unit = (new Unit())
			->setStore($store)
			->setCode('pc-' . substr(md5($slug), 0, 8))
			->setName('Piece')
			->setPrecision(0);
		$warehouse = (new Warehouse())
			->setStore($store)
			->setName('Warehouse ' . $slug);
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($unit)
			->setName('Product ' . $slug)
			->setCode('product-' . substr(md5($slug), 0, 8))
			->setCanBeSold(true)
			->setCanBePurchased(true)
			->setCanBeManufactured(false)
			->setProductKind(ProductKindEnum::FINISHED_PRODUCT);

		$this->entityManager->persist($store);
		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($warehouse);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return [$store, $warehouse, $product];
	}

	private function createPurchase(
		Store $store,
		Warehouse $warehouse,
		Product $product,
		PurchaseStatus $status,
		string $quantity,
		string $receivedQuantity,
	): Purchase {
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber('PO-' . substr(uniqid(), -8))
			->setStatus($status)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setSupplierNameSnapshot('Supplier')
			->setTotalAmount('20.0000')
			->setTotalAmountBase('20.0000');
		$entry = (new PurchaseEntry())
			->setPurchase($purchase)
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity($quantity)
			->setReceivedQuantity($receivedQuantity)
			->setUnitCost('4.0000')
			->setUnitCostBase('4.0000')
			->setTotalCost('20.0000')
			->setTotalCostBase('20.0000')
			->setProductNameSnapshot((string) $product->getName())
			->setProductCodeSnapshot((string) $product->getCode())
			->setUnitCodeSnapshot('pc')
			->setUnitNameSnapshot('Piece');

		$purchase->addPurchaseEntry($entry);
		$this->entityManager->persist($purchase);
		$this->entityManager->persist($entry);
		$this->entityManager->flush();

		return $purchase;
	}

	private function createCurrency(): Currency
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
}

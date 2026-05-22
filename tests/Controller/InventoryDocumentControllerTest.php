<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\InventoryReason;
use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Entity\StockMovement;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Entity\Product;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\InventoryReasonType;
use App\Enum\ProductKindEnum;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InventoryDocumentControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testIndexRequiresAuthenticatedAdminUser(): void
	{
		$this->client->request('GET', '/admin/store/1/inventory-document/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexIsAvailableForAdminUser(): void
	{
		$this->client->loginUser($this->createUser('inventory-document-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('inventory-document-index-' . uniqid());
		$this->createInventoryDocument($store, $warehouse, $product, 'ID-' . uniqid());

		$this->client->request('GET', sprintf('/admin/store/%d/inventory-document/', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertPageTitleContains('Inventory documents');
		self::assertSelectorTextContains('body', 'Inventory documents');
	}

	public function testShowDisplaysHeaderLinesAndStockMovements(): void
	{
		$this->client->loginUser($this->createUser('inventory-document-show-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('inventory-document-show-' . uniqid());
		$document = $this->createInventoryDocument($store, $warehouse, $product, 'ID-SHOW-' . uniqid(), InventoryDocumentStatus::POSTED);
		$line = $document->getLines()->first();
		$warehouseStock = $this->createWarehouseStock($warehouse, $product, '5.0000', '4.0000');
		$movement = (new StockMovement())
			->setWarehouseStock($warehouseStock)
			->setQuantityChange('2.0000')
			->setUnitCost('4.0000')
			->setBalanceAfter('5.0000');
		$line->setWarehouseStock($warehouseStock);
		$line->addStockMovement($movement);
		$this->entityManager->persist($movement);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-document/%d/show',
			$store->getId(),
			$document->getId()
		));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', (string) $document->getNumber());
		self::assertSelectorTextContains('body', $product->getName());
		self::assertSelectorTextContains('body', $warehouse->getName());
		self::assertSelectorTextContains('body', 'Stock movements');
		self::assertSelectorTextContains('body', '5.0000');
	}

	public function testShowRejectsInventoryDocumentFromAnotherStore(): void
	{
		$this->client->loginUser($this->createUser('inventory-document-scope-admin-' . uniqid() . '@example.com'));
		[$store] = $this->createStoreWarehouseAndProduct('inventory-document-scope-' . uniqid());
		[$anotherStore, $warehouse, $product] = $this->createStoreWarehouseAndProduct('inventory-document-other-' . uniqid());
		$document = $this->createInventoryDocument($anotherStore, $warehouse, $product, 'ID-SCOPE-' . uniqid());

		$this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-document/%d/show',
			$store->getId(),
			$document->getId()
		));

		self::assertResponseStatusCodeSame(404);
	}

	public function testHistoryDisplaysStatusTimeline(): void
	{
		$this->client->loginUser($this->createUser('inventory-document-history-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('inventory-document-history-' . uniqid());
		$document = $this->createInventoryDocument($store, $warehouse, $product, 'ID-HISTORY-' . uniqid());
		$this->createStatusHistory($store, $document, 'draft', 'posted', 'Posted from test');

		$this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-document/%d/history',
			$store->getId(),
			$document->getId()
		));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'draft -> posted');
		self::assertSelectorTextContains('body', 'Posted from test');
	}

	public function testPostActionPostsDraftDocument(): void
	{
		$this->client->loginUser($this->createUser('inventory-document-post-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('inventory-document-post-' . uniqid());
		$document = $this->createInventoryDocument($store, $warehouse, $product, 'ID-POST-' . uniqid());

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-document/%d/show',
			$store->getId(),
			$document->getId()
		));
		self::assertResponseIsSuccessful();

		$this->client->submit($crawler->selectButton('Post')->form());

		self::assertResponseRedirects(sprintf('/admin/store/%d/inventory-document/?id=%d', $store->getId(), $document->getId()));

		$postedDocument = $this->entityManager->getRepository(InventoryDocument::class)->find($document->getId());

		self::assertInstanceOf(InventoryDocument::class, $postedDocument);
		self::assertSame(InventoryDocumentStatus::POSTED, $postedDocument->getStatus());
		self::assertCount(1, $postedDocument->getLines()->first()->getStockMovements());
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
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->createCurrency());
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

	private function createInventoryDocument(
		Store $store,
		Warehouse $warehouse,
		Product $product,
		string $number,
		InventoryDocumentStatus $status = InventoryDocumentStatus::DRAFT,
	): InventoryDocument {
		$document = (new InventoryDocument())
			->setStore($store)
			->setNumber($number)
			->setType(InventoryDocumentType::STOCK_ADJUSTMENT)
			->setStatus($status)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setTotalAmount('8.0000')
			->setTotalAmountBase('8.0000')
			->setReason($this->createInventoryReason($store, 'Stock adjustment ' . $number, InventoryReasonType::STOCK_ADJUSTMENT))
			->setComment('Inventory document test');

		if ($status === InventoryDocumentStatus::POSTED) {
			$document->setPostedAt(new DateTimeImmutable());
		}

		if ($status === InventoryDocumentStatus::CANCELED) {
			$document->setCanceledAt(new DateTimeImmutable());
		}

		$document->addLine((new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection(InventoryDirection::IN)
			->setQuantity('2.0000')
			->setUnitPrice('4.0000')
			->setUnitPriceBase('4.0000')
			->setTotalPrice('8.0000')
			->setTotalPriceBase('8.0000'));

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	private function createInventoryReason(Store $store, string $name, InventoryReasonType $type): InventoryReason
	{
		$inventoryReason = (new InventoryReason())
			->setStore($store)
			->setName($name)
			->setType($type);

		$this->entityManager->persist($inventoryReason);

		return $inventoryReason;
	}

	private function createWarehouseStock(Warehouse $warehouse, Product $product, string $quantity, string $averageCost): WarehouseStock
	{
		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setQuantityOnHand($quantity)
			->setAverageCost($averageCost);

		$this->entityManager->persist($warehouseStock);
		$this->entityManager->flush();

		return $warehouseStock;
	}

	private function createStatusHistory(
		Store $store,
		InventoryDocument $document,
		?string $oldStatus,
		string $newStatus,
		string $comment,
	): StatusHistory {
		$history = (new StatusHistory())
			->setStore($store)
			->setEntityType(StatusHistoryEntityType::INVENTORY_DOCUMENT)
			->setEntityId((int) $document->getId())
			->setOldStatus($oldStatus)
			->setNewStatus($newStatus)
			->setComment($comment)
			->setChangedAt(new DateTimeImmutable());

		$this->entityManager->persist($history);
		$this->entityManager->flush();

		return $history;
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

<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class OrderShipmentControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testShipActionCreatesDraftSaleShipmentAndPostingShipsOrder(): void
	{
		$this->client->loginUser($this->createUser('order-shipment-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-shipment-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '2.0000', '0.0000');
		$this->createWarehouseStock($warehouse, $product, '2.0000', '6.0000');

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/order/%d/show',
			$store->getId(),
			$order->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertGreaterThan(0, $crawler->selectButton('Ship')->count());

		// The Ship button opens the selective shipment modal; submit its form (quantities default to the
		// remaining quantity of each position, so this ships everything that is left).
		$this->client->submit($crawler->selectButton('Create shipment')->form());

		$document = $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::SALE_SHIPMENT,
		]);

		self::assertInstanceOf(InventoryDocument::class, $document);
		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $order->getId()));
		self::assertSame(InventoryDocumentStatus::DRAFT, $document->getStatus());
		self::assertSame($store->getId(), $document->getStore()?->getId());
		self::assertSame($order->getId(), $document->getOrder()?->getId());
		self::assertSame($store->getBaseCurrency()?->getCode(), $document->getCurrency()?->getCode());
		self::assertSame('1.00000000', $document->getExchangeRateToBase());
		self::assertCount(1, $document->getLines());

		$line = $document->getLines()->first();

		self::assertSame(InventoryDirection::OUT, $line->getDirection());
		self::assertSame('2.0000', $line->getQuantity());
		self::assertSame('10.0000', $line->getUnitPrice());
		self::assertSame('10.0000', $line->getUnitPriceBase());
		self::assertSame($order->getOrderEntries()->first()->getId(), $line->getOrderEntry()?->getId());

		$this->client->request('GET', sprintf(
			'/admin/store/%d/order/%d/show',
			$store->getId(),
			$order->getId(),
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
		self::assertSelectorTextContains('body', 'Order ' . $order->getNumber());
		$this->client->submit($this->client->getCrawler()->selectButton('Post')->form());
		self::assertResponseRedirects(sprintf('/admin/store/%d/inventory-document/?id=%d', $store->getId(), $document->getId()));

		$this->entityManager->clear();
		$shippedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
		$warehouseStock = $this->entityManager->getRepository(WarehouseStock::class)->findOneBy([
			'warehouse' => $warehouse,
			'product' => $product,
		]);

		self::assertInstanceOf(Order::class, $shippedOrder);
		self::assertInstanceOf(WarehouseStock::class, $warehouseStock);
		self::assertSame(OrderStatus::SHIPPED, $shippedOrder->getStatus());
		self::assertSame('2.0000', $shippedOrder->getOrderEntries()->first()->getShippedQuantity());
		self::assertSame('0.0000', $warehouseStock->getQuantityOnHand());

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/order/%d/show',
			$store->getId(),
			$order->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertGreaterThan(0, $crawler->selectButton('Mark delivered')->count());

		$this->client->submit($crawler->selectButton('Mark delivered')->form());
		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $order->getId()));

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/order/%d/show',
			$store->getId(),
			$order->getId(),
		));
		self::assertResponseIsSuccessful();
		self::assertGreaterThan(0, $crawler->selectButton('Complete')->count());

		$this->client->submit($crawler->selectButton('Complete')->form());
		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $order->getId()));

		$this->entityManager->clear();
		$deliveredOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());

		self::assertInstanceOf(Order::class, $deliveredOrder);
		self::assertSame(OrderStatus::DELIVERED, $deliveredOrder->getStatus());
	}

	public function testShipActionRejectsInvalidCsrfToken(): void
	{
		$this->client->loginUser($this->createUser('order-shipment-csrf-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-shipment-csrf-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '1.0000', '0.0000');

		$this->client->request('POST', sprintf(
			'/admin/store/%d/order/%d/ship',
			$store->getId(),
			$order->getId(),
		), [
			'_token' => 'invalid',
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $order->getId()));
		self::assertNull($this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::SALE_SHIPMENT,
		]));
	}

	public function testSelectivePartialShipmentShipsOnlySelectedQuantity(): void
	{
		$this->client->loginUser($this->createUser('order-partial-ship-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-partial-ship-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '2.0000', '0.0000');
		$this->createWarehouseStock($warehouse, $product, '2.0000', '6.0000');
		$entryId = $order->getOrderEntries()->first()->getId();

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		self::assertResponseIsSuccessful();

		// Ship only 1 of the 2 ordered units; the rest stays for a later shipment.
		$this->client->submit($crawler->selectButton('Create shipment')->form(), ['lines' => [$entryId => '1']]);

		$document = $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::SALE_SHIPMENT,
		]);

		self::assertInstanceOf(InventoryDocument::class, $document);
		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $order->getId()));
		self::assertCount(1, $document->getLines());
		self::assertSame('1.0000', $document->getLines()->first()->getQuantity());
	}

	public function testShipmentModalAjaxSubmitCreatesDraftAndReturnsOrderFragments(): void
	{
		$this->client->loginUser($this->createUser('order-shipment-ajax-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-shipment-ajax-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '2.0000', '0.0000');
		$this->createWarehouseStock($warehouse, $product, '2.0000', '6.0000');
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler->selectButton('Create shipment')->form();

		$this->client->request(
			$form->getMethod(),
			$form->getUri(),
			$form->getValues(),
			[],
			[
				'HTTP_ACCEPT' => 'application/json',
				'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
			],
		);

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('Order shipment draft created. Review and post the inventory document to ship stock.', $data['flashes'][0]['message'] ?? null);
		self::assertArrayHasKey('card', $data['fragments']);
		self::assertArrayHasKey('row', $data['fragments']);
		self::assertInstanceOf(InventoryDocument::class, $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::SALE_SHIPMENT,
		]));
	}

	public function testShipmentModalAjaxSubmitShowsValidationErrorWhenNoPositionsSelected(): void
	{
		$this->client->loginUser($this->createUser('order-shipment-empty-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-shipment-empty-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '2.0000', '0.0000');
		$this->createWarehouseStock($warehouse, $product, '2.0000', '6.0000');
		$entryId = (int) $order->getOrderEntries()->first()->getId();
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler->selectButton('Create shipment')->form();
		$values = $form->getValues();
		$values['lines'][$entryId] = '0';

		$this->client->request(
			$form->getMethod(),
			$form->getUri(),
			$values,
			[],
			[
				'HTTP_ACCEPT' => 'application/json',
				'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
			],
		);

		self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('No positions were selected to ship.', $data['flashes'][0]['message'] ?? null);
		self::assertSame([], $data['fragments']);
		self::assertNull($this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::SALE_SHIPMENT,
		]));
	}

	public function testReturnModalAjaxSubmitCreatesDraftAndReturnsOrderFragments(): void
	{
		$this->client->loginUser($this->createUser('order-return-ajax-admin-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-return-ajax-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '2.0000', '0.0000');
		$this->createWarehouseStock($warehouse, $product, '2.0000', '6.0000');

		// Ship everything and post the shipment so the position becomes returnable. Follow the
		// redirects so the setup flashes are consumed and don't leak into the AJAX assertion below.
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		$this->client->submit($crawler->selectButton('Create shipment')->form());
		$this->client->followRedirect();

		$shipment = $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::SALE_SHIPMENT,
		]);
		self::assertInstanceOf(InventoryDocument::class, $shipment);

		$this->client->request('GET', sprintf('/admin/store/%d/inventory-document/%d/show', $store->getId(), $shipment->getId()));
		$this->client->submit($this->client->getCrawler()->selectButton('Post')->form());
		$this->client->followRedirect();

		// Open the order and submit the Return modal as AJAX; it must close in place (JSON fragments),
		// not redirect to the inventory-document list. Select the form inside the return modal (the
		// card-footer button that opens the modal shares the same label in EN, so navigate by modal ID).
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler->filter('[id^="order-return-modal-"] form')->form();

		$this->client->request(
			$form->getMethod(),
			$form->getUri(),
			$form->getValues(),
			[],
			[
				'HTTP_ACCEPT' => 'application/json',
				'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
			],
		);

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('Customer return draft created. Review and post the inventory document to return stock.', $data['flashes'][0]['message'] ?? null);
		self::assertArrayHasKey('card', $data['fragments']);
		self::assertArrayHasKey('row', $data['fragments']);
		self::assertInstanceOf(InventoryDocument::class, $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'order' => $order,
			'type' => InventoryDocumentType::CUSTOMER_RETURN,
		]));
	}

	public function testRefuseEntryRemainingSetsCanceledQuantity(): void
	{
		$this->client->loginUser($this->createUser('order-refuse-' . uniqid() . '@example.com'));
		[$store, $warehouse, $product] = $this->createStoreWarehouseAndProduct('order-refuse-' . uniqid());
		$order = $this->createOrder($store, $warehouse, $product, OrderStatus::READY_TO_SHIP, '5.0000', '0.0000');
		$this->createWarehouseStock($warehouse, $product, '5.0000', '6.0000');
		$orderId = $order->getId();

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $orderId));
		self::assertResponseIsSuccessful();

		$this->client->submit($crawler->selectButton('Confirm refusal')->form(), ['quantity' => '2']);
		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $orderId));

		$this->entityManager->clear();
		$refusedOrder = $this->entityManager->getRepository(Order::class)->find($orderId);

		self::assertInstanceOf(Order::class, $refusedOrder);
		self::assertSame('2.0000', $refusedOrder->getOrderEntries()->first()->getCanceledQuantity());
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

	private function createOrder(
		Store $store,
		Warehouse $warehouse,
		Product $product,
		OrderStatus $status,
		string $quantity,
		string $shippedQuantity,
	): Order {
		$order = (new Order())
			->setStore($store)
			->setNumber('SO-' . substr(uniqid(), -8))
			->setStatus($status)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setCustomerNameSnapshot('Customer')
			->setTotalAmount('20.0000')
			->setTotalAmountBase('20.0000');
		$entry = (new OrderEntry())
			->setOrder($order)
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity($quantity)
			->setShippedQuantity($shippedQuantity)
			->setUnitPrice('10.0000')
			->setUnitPriceBase('10.0000')
			->setTotalPrice('20.0000')
			->setTotalPriceBase('20.0000')
			->setProductNameSnapshot((string) $product->getName())
			->setProductCodeSnapshot((string) $product->getCode())
			->setUnitCodeSnapshot('pc')
			->setUnitNameSnapshot('Piece');

		$order->addOrderEntry($entry);
		$this->entityManager->persist($order);
		$this->entityManager->persist($entry);
		$this->entityManager->flush();

		return $order;
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

<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\ProductKindEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderBuilderControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testNewOrderBuilderPageIsAvailable(): void
	{
		$this->client->loginUser($this->createUser('order-builder-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-store-' . uniqid());
		$this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Нове замовлення');
		self::assertSelectorExists('[data-controller~="order-form"]');
		self::assertSelectorExists('[data-order-form-target="prototype"]');
	}

	public function testNewOrderBuilderFormCanBeSubmitted(): void
	{
		$this->client->loginUser($this->createUser('order-builder-submit-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-submit-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Зберегти', [
			'order[customer]' => $customer->getId(),
			'order[customerPhone]' => $customer->getPhone(),
			'order[customerName]' => $customer->getName(),
			'order[customerLastName]' => $customer->getLastName(),
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
			'order[deliveryAddress]' => 'Test delivery address',
			'order[comment]' => 'Test comment',
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/', $store->getId()));
		self::assertNotNull($this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]));
	}

	public function testNewOrderBuilderFormCreatesCustomerWhenCustomerIsNotSelected(): void
	{
		$this->client->loginUser($this->createUser('order-builder-new-customer-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-new-customer-store-' . uniqid());
		$phone = '050 123-45-67';

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Зберегти', [
			'order[customerPhone]' => $phone,
			'order[customerName]' => 'Inline',
			'order[customerLastName]' => 'Customer',
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/', $store->getId()));

		$customer = $this->entityManager->getRepository(Customer::class)->findOneBy([
			'store' => $store,
			'phone' => '+380501234567',
		]);

		self::assertInstanceOf(Customer::class, $customer);
		self::assertSame('Inline', $customer->getName());
		self::assertSame('Customer', $customer->getLastName());
		self::assertSame('+380501234567', $customer->getPhone());
		self::assertNotNull($this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]));
	}

	public function testNewOrderBuilderFormCanBeSubmittedWithEntry(): void
	{
		$this->client->loginUser($this->createUser('order-builder-entry-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-entry-store-' . uniqid());
		$customer = $this->createCustomer($store);
		$product = $this->createProduct($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'orderEntries' => [
					[
						'product' => $product->getId(),
						'quantity' => '2',
						'unitPrice' => '10.0000',
						'discountAmount' => '1.0000',
					],
				],
				'_token' => $token,
			],
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/', $store->getId()));

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);

		self::assertInstanceOf(Order::class, $order);
		self::assertSame('19.0000', $order->getTotalAmount());
		self::assertCount(1, $order->getOrderEntries());
	}

	public function testOrderEntryValidationErrorsAreRendered(): void
	{
		$this->client->loginUser($this->createUser('order-builder-entry-error-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-entry-error-store-' . uniqid());
		$customer = $this->createCustomer($store);
		$product = $this->createProduct($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'orderEntries' => [
					[
						'product' => $product->getId(),
						'quantity' => '0',
						'unitPrice' => '10.0000',
					],
				],
				'_token' => $token,
			],
		]);

		self::assertResponseStatusCodeSame(422);
		self::assertSelectorTextContains('.order-entry-item .form-error', 'Quantity must be greater than zero.');
	}

	public function testPcsOrderEntryQuantityMustBeInteger(): void
	{
		$this->client->loginUser($this->createUser('order-builder-entry-integer-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-entry-integer-store-' . uniqid());
		$customer = $this->createCustomer($store);
		$product = $this->createProduct($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'orderEntries' => [
					[
						'product' => $product->getId(),
						'quantity' => '1.0000',
						'unitPrice' => '10.0000',
					],
				],
				'_token' => $token,
			],
		]);

		self::assertResponseStatusCodeSame(422);
		self::assertSelectorTextContains('.order-entry-item .form-error', 'Quantity must be an integer.');
	}

	public function testStockOrderEntryQuantityCannotExceedAvailableQuantity(): void
	{
		$this->client->loginUser($this->createUser('order-builder-entry-stock-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-entry-stock-store-' . uniqid());
		$customer = $this->createCustomer($store);
		$product = $this->createProduct($store);
		$warehouse = $this->createWarehouse($store);
		$this->createWarehouseStock($warehouse, $product, '2.00', '1.0000');

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'orderEntries' => [
					[
						'product' => $product->getId(),
						'warehouse' => $warehouse->getId(),
						'quantity' => '2',
						'unitPrice' => '10.0000',
					],
				],
				'_token' => $token,
			],
		]);

		self::assertResponseStatusCodeSame(422);
		self::assertSelectorTextContains('.order-entry-item .form-error', 'Insufficient stock. Available quantity: 1.0000.');
	}

	public function testProductOptionsAreLimitedToCurrentStore(): void
	{
		$this->client->loginUser($this->createUser('order-builder-options-admin-' . uniqid() . '@example.com'));
		$currentStore = $this->createStore('order-builder-options-store-' . uniqid());
		$otherStore = $this->createStore('order-builder-options-other-store-' . uniqid());
		$currentProduct = $this->createProduct($currentStore, 'Shared search product current');
		$otherProduct = $this->createProduct($otherStore, 'Shared search product other');

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Shared%%20search',
			$currentStore->getId()
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		$ids = array_column($data['products'], 'id');

		self::assertContains($currentProduct->getId(), $ids);
		self::assertNotContains($otherProduct->getId(), $ids);
	}

	public function testCustomerSearchReturnsOnlyCurrentStoreCustomersByPhone(): void
	{
		$this->client->loginUser($this->createUser('order-builder-customer-search-admin-' . uniqid() . '@example.com'));
		$currentStore = $this->createStore('order-builder-customer-search-store-' . uniqid());
		$otherStore = $this->createStore('order-builder-customer-search-other-store-' . uniqid());
		$currentCustomer = $this->createCustomer($currentStore);
		$otherCustomer = $this->createCustomer($otherStore);
		$currentCustomer->setPhone('+380501234567');
		$otherCustomer->setPhone('+380501234567');
		$this->entityManager->flush();

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/customer-search?phone=05012',
			$currentStore->getId(),
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		$ids = array_column($data['customers'], 'id');

		self::assertContains($currentCustomer->getId(), $ids);
		self::assertNotContains($otherCustomer->getId(), $ids);
	}

	public function testProductOptionsCanBePaginated(): void
	{
		$this->client->loginUser($this->createUser('order-builder-options-page-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-options-page-store-' . uniqid());
		$firstProduct = $this->createProduct($store, 'Paged search product A');
		$secondProduct = $this->createProduct($store, 'Paged search product B');

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Paged%%20search&page=1&limit=1',
			$store->getId()
		));

		self::assertResponseIsSuccessful();
		$firstPage = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertTrue($firstPage['hasMore']);
		self::assertSame(1, $firstPage['page']);
		self::assertCount(1, $firstPage['products']);
		self::assertSame($firstProduct->getId(), $firstPage['products'][0]['id']);

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Paged%%20search&page=2&limit=1',
			$store->getId()
		));

		self::assertResponseIsSuccessful();
		$secondPage = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertFalse($secondPage['hasMore']);
		self::assertSame(2, $secondPage['page']);
		self::assertCount(1, $secondPage['products']);
		self::assertSame($secondProduct->getId(), $secondPage['products'][0]['id']);
	}

	public function testProductSearchExcludesAlreadySelectedOrderEntryOptions(): void
	{
		$this->client->loginUser($this->createUser('order-builder-options-exclude-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-options-exclude-store-' . uniqid());
		$product = $this->createProduct($store, 'Filtered option product');
		$firstWarehouse = $this->createWarehouse($store);
		$secondWarehouse = $this->createWarehouse($store);
		$this->createWarehouseStock($firstWarehouse, $product, '5.00');
		$this->createWarehouseStock($secondWarehouse, $product, '7.00');

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Filtered%%20option&excludedOptions[]=stock:%d:%d',
			$store->getId(),
			$product->getId(),
			$firstWarehouse->getId(),
		));

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertCount(1, $data['products']);
		self::assertSame($product->getId(), $data['products'][0]['id']);
		self::assertCount(1, $data['products'][0]['stockOptions']);
		self::assertSame($secondWarehouse->getId(), $data['products'][0]['stockOptions'][0]['warehouseId']);

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Filtered%%20option&excludedOptions[]=stock:%d:%d&excludedOptions[]=stock:%d:%d',
			$store->getId(),
			$product->getId(),
			$firstWarehouse->getId(),
			$product->getId(),
			$secondWarehouse->getId(),
		));

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame([], $data['products']);
	}

	public function testProductSearchExcludesAlreadySelectedProductionOption(): void
	{
		$this->client->loginUser($this->createUser('order-builder-options-production-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-options-production-store-' . uniqid());
		$product = $this->createProduct($store, 'Filtered production product', true);

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Filtered%%20production&excludedOptions[]=production:%d',
			$store->getId(),
			$product->getId(),
		));

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame([], $data['products']);
	}

	public function testOrderSummaryCanBeCalculatedByAjax(): void
	{
		$this->client->loginUser($this->createUser('order-builder-summary-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-summary-store-' . uniqid());
		$product = $this->createProduct($store);

		$this->client->request('POST', sprintf('/api/admin/store/%d/order/summary', $store->getId()), [
			'order' => [
				'orderEntries' => [
					'0' => [
						'product' => $product->getId(),
						'quantity' => '2.0000',
						'unitPrice' => '15.0000',
						'discountAmount' => '5.0000',
					],
				],
			],
		], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame(1, $data['count']);
		self::assertSame('30.00', $data['subtotal']);
		self::assertSame('5.00', $data['discount']);
		self::assertSame('25.00', $data['total']);
		self::assertSame('25.00', $data['lineTotals'][0]);
	}

	public function testOrderSummaryKeepsExistingEntryWhenProductIsNotSubmitted(): void
	{
		$this->client->loginUser($this->createUser('order-builder-summary-existing-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-summary-existing-store-' . uniqid());

		$this->client->request('POST', sprintf('/api/admin/store/%d/order/summary', $store->getId()), [
			'order' => [
				'orderEntries' => [
					'0' => [
						'quantity' => '3.0000',
						'unitPrice' => '10.0000',
						'discountAmount' => '2.0000',
					],
				],
			],
		], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame(1, $data['count']);
		self::assertSame('30.00', $data['subtotal']);
		self::assertSame('2.00', $data['discount']);
		self::assertSame('28.00', $data['total']);
		self::assertSame('28.00', $data['lineTotals'][0]);
	}

	public function testOrderSummaryAcceptsDiscountAlias(): void
	{
		$this->client->loginUser($this->createUser('order-builder-summary-discount-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-summary-discount-store-' . uniqid());
		$product = $this->createProduct($store);

		$this->client->request('POST', sprintf('/api/admin/store/%d/order/summary', $store->getId()), [
			'order' => [
				'orderEntries' => [
					'0' => [
						'product' => $product->getId(),
						'quantity' => '4.0000',
						'unitPrice' => '10.0000',
						'discount' => '7.0000',
					],
				],
			],
		], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame('40.00', $data['subtotal']);
		self::assertSame('7.00', $data['discount']);
		self::assertSame('33.00', $data['total']);
		self::assertSame('33.00', $data['lineTotals'][0]);
	}

	public function testExistingOrderEntryKeepsProductWhenEditFormIsSubmittedWithoutProductField(): void
	{
		$this->client->loginUser($this->createUser('order-builder-edit-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-edit-store-' . uniqid());
		$customer = $this->createCustomer($store);
		$product = $this->createProduct($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'orderEntries' => [
					[
						'product' => $product->getId(),
						'quantity' => '1',
						'unitPrice' => '10.0000',
					],
				],
				'_token' => $token,
			],
		]);

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);
		self::assertInstanceOf(Order::class, $order);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()));
		self::assertSame('1', $crawler->filter('[data-order-entry-target~="quantity"]')->attr('value'));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'orderEntries' => [
					[
						'quantity' => '3',
						'unitPrice' => '10.0000',
					],
				],
				'_token' => $token,
			],
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d', $store->getId(), $order->getId()));
		$this->entityManager->refresh($order);
		self::assertSame('30.0000', $order->getTotalAmount());
		self::assertSame($product->getId(), $order->getOrderEntries()->first()->getProduct()?->getId());
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

	private function createStore(string $slug): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->createCurrency());

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
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

	private function createCustomer(Store $store): Customer
	{
		$customer = (new Customer())
			->setName('Order customer ' . uniqid())
			->setLastName('Test')
			->setPhone('+380' . random_int(100000000, 999999999))
			->setEmail('order-customer-' . uniqid() . '@example.com')
			->setStore($store);

		$this->entityManager->persist($customer);
		$this->entityManager->flush();

		return $customer;
	}

	private function createProduct(Store $store, ?string $name = null, bool $canBeManufactured = false): Product
	{
		$category = (new Category())
			->setName('Order category ' . uniqid())
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
			->setName($name ?? 'Order product ' . uniqid())
			->setCode('order-product-' . uniqid())
			->setBaseSalePrice('10.0000')
			->setCanBeSold(true)
			->setCanBePurchased(false)
			->setCanBeManufactured($canBeManufactured);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return $product;
	}

	private function createWarehouse(Store $store): Warehouse
	{
		$warehouse = (new Warehouse())
			->setName('Order warehouse ' . uniqid())
			->setStore($store);

		$this->entityManager->persist($warehouse);
		$this->entityManager->flush();

		return $warehouse;
	}

	private function createWarehouseStock(Warehouse $warehouse, Product $product, string $quantityOnHand, string $reservedQuantity = '0.0000'): WarehouseStock
	{
		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setQuantityOnHand($quantityOnHand)
			->setReservedQuantity($reservedQuantity)
			->setAverageCost('0.0000');
		$product->addWarehouseStock($warehouseStock);

		$this->entityManager->persist($warehouseStock);
		$this->entityManager->flush();

		return $warehouseStock;
	}
}

<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\ExchangeRate;
use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\ProductDiscountTarget;
use App\Entity\ProductRelation;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTypeEnum;
use App\Enum\ProductDiscountTargetTypeEnum;
use App\Enum\ProductKindEnum;
use App\Enum\ProductRelationTypeEnum;
use DateTime;
use DateTimeImmutable;
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
		self::assertSelectorTextContains('body', 'New order');
		self::assertSelectorExists('[data-controller~="order-form"]');
		self::assertSelectorExists('[data-controller~="draft-order-comments"]');
		self::assertSelectorExists('[data-order-form-target="prototype"]');
	}

	public function testOrderIndexShowsFiltersAboveListWithoutFilterTab(): void
	{
		$this->client->loginUser($this->createUser('order-index-filter-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-index-filter-store-' . uniqid());

		$this->client->request('GET', sprintf('/admin/store/%d/order/?order_filter%%5Bnumber%%5D=SO', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('#order-quick-search');
		self::assertSelectorExists('button[data-bs-target="#order_filter-advanced-filters"]');
		self::assertSelectorExists('#order_filter-advanced-filters form[name="order_filter"]');
		self::assertSelectorExists('.order-filter-chips');
		self::assertSelectorNotExists('.tab-search-link');
	}

	public function testOrderIndexRestoresActiveTabFromQuery(): void
	{
		$this->client->loginUser($this->createUser('order-index-active-tab-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-index-active-tab-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-active-tab-' . uniqid())
			->setCustomerNameSnapshot('Active Tab Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('0.0000');

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		foreach ([
			'show' => 'tab-1',
			'payments' => 'tab-2',
			'comments' => 'tab-3',
			'customer' => 'tab-4',
			'history' => 'tab-5',
		] as $tabKey => $paneId) {
			$this->client->request('GET', sprintf('/admin/store/%d/order/?id=%d&active_tab=%s', $store->getId(), $order->getId(), $tabKey));

			self::assertResponseIsSuccessful();
			self::assertSelectorExists(sprintf('.nav-link.active[data-tab-key="%s"]', $tabKey));
			self::assertSelectorExists(sprintf('#%s.tab-pane.active', $paneId));
		}
	}

	public function testOrderIndexFallsBackToShowTabForInvalidActiveTab(): void
	{
		$this->client->loginUser($this->createUser('order-index-invalid-tab-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-index-invalid-tab-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-invalid-tab-' . uniqid())
			->setCustomerNameSnapshot('Invalid Tab Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('0.0000');

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/order/?id=%d&active_tab=bad-tab', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.nav-link.active[data-tab-key="show"]');
		self::assertSelectorExists('#tab-1.tab-pane.active');
	}

	public function testOrderIndexRestoresSelectedOrderFromQuery(): void
	{
		$this->client->loginUser($this->createUser('order-index-selected-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-index-selected-store-' . uniqid());
		$firstOrder = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-selected-first-' . uniqid())
			->setCustomerNameSnapshot('First Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('0.0000');
		$secondOrder = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-selected-second-' . uniqid())
			->setCustomerNameSnapshot('Second Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('0.0000');

		$this->entityManager->persist($firstOrder);
		$this->entityManager->persist($secondOrder);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/order/?id=%d', $store->getId(), $firstOrder->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists(sprintf('tr.table-active[id="%d"]', $firstOrder->getId()));
		self::assertSelectorTextContains('.order-show-card', 'First Customer');
	}

	public function testOrderShowGroupsDetailsWithFallbacksAndAlignedAmounts(): void
	{
		$this->client->loginUser($this->createUser('order-show-details-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-show-details-store-' . uniqid());
		$product = $this->createProduct($store, 'Show card desk');
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-show-' . uniqid())
			->setCustomerNameSnapshot('Marina Kurceva')
			->setCustomerPhoneSnapshot('+380956554307')
			->setCustomerEmailSnapshot(null)
			->setDeliveryAddress(null)
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('0.0000');
		$entry = (new OrderEntry())
			->setProduct($product)
			->setProductNameSnapshot('Show card desk')
			->setProductCodeSnapshot($product->getCode())
			->setUnitCodeSnapshot($product->getUnit()?->getCode() ?? 'pc')
			->setUnitNameSnapshot($product->getUnit()?->getName() ?? 'Piece')
			->setQuantity('1.0000')
			->setUnitPrice('10000.0000')
			->setUnitPriceBase('10000.0000')
			->setTotalPrice('10000.0000')
			->setTotalPriceBase('10000.0000');
		$order->addOrderEntry($entry);

		$this->entityManager->persist($order);
		$this->entityManager->persist($entry);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.order-show-grid');
		self::assertSelectorTextContains('.order-show-card', 'Customer');
		self::assertSelectorTextContains('.order-show-card', 'Payment');
		self::assertSelectorTextContains('.order-show-card', 'Delivery');
		self::assertSelectorTextContains('.order-show-card', 'Products');
		self::assertSelectorTextContains('.order-show-card', 'Inventory documents');
		self::assertSelectorTextContains('.order-show-card', 'Not specified');
		self::assertSelectorTextContains('.order-show-money-list', '10 000.00 UAH');
		self::assertSelectorTextContains('.order-show-product-card', 'Show card desk');
		self::assertSelectorTextContains('.order-show-product-card', '1 pc');
		self::assertSelectorTextContains('.order-show-product-card', '10 000.00 UAH');
		self::assertSelectorExists('.order-show-product-card .order-copy-action[data-copy-feedback="Copied"]');
		self::assertSelectorExists('.order-show-product-card [data-copy-feedback][aria-live="polite"]');
		self::assertSelectorExists('.order-show-product-action[aria-label="Open product"]');
	}

	public function testOrderPaymentsTabUsesPaymentCardsInsteadOfTable(): void
	{
		$this->client->loginUser($this->createUser('order-payment-cards-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-payment-cards-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-payments-' . uniqid())
			->setCustomerNameSnapshot('Paid Customer')
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('10000.0000')
			->setPaymentStatus(PaymentStatusEnum::PAID);
		$payment = (new Payment())
			->setStore($store)
			->setOrder($order)
			->setCurrency($store->getBaseCurrency())
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount('10000.0000')
			->setAmountBase('10000.0000')
			->setPaidAt(new DateTimeImmutable('2026-05-30 17:14:00'));
		$order->addPayment($payment);

		$this->entityManager->persist($order);
		$this->entityManager->persist($payment);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/payment/order/%d/document', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSame(0, $this->client->getCrawler()->selectLink('Full form')->count());
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add incoming payment')->count());
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add outgoing refund')->count());
		self::assertSelectorNotExists('.table-responsive table');
		self::assertSelectorExists('.document-payment-list');
		self::assertSelectorTextContains('.document-payment-card', 'Payment #1');
		self::assertSelectorTextContains('.document-payment-card', '30.05.2026 17:14');
		self::assertSelectorTextContains('.document-payment-card', 'Incoming · Cash');
		self::assertSelectorTextContains('.document-payment-card', '10 000.00 UAH');
		self::assertSelectorTextContains('.document-payment-card', 'Correction');
		self::assertSelectorTextContains('.document-payment-card', '—');
		self::assertSelectorTextContains('.document-payment-status', 'This document is fully paid.');
	}

	public function testUnpaidOrderPaymentsTabShowsIncomingPaymentAction(): void
	{
		$this->client->loginUser($this->createUser('order-payment-action-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-payment-action-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-payment-action-' . uniqid())
			->setStatus(OrderStatus::CONFIRMED)
			->setCustomerNameSnapshot('Unpaid Customer')
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('2500.0000')
			->setPaymentStatus(PaymentStatusEnum::PARTIALLY_PAID);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/payment/order/%d/document', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSame(1, $this->client->getCrawler()->selectLink('Add incoming payment')->count());
		self::assertSame(0, $this->client->getCrawler()->selectLink('Full form')->count());
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add outgoing refund')->count());
		self::assertSame(1, $this->client->getCrawler()->filterXPath('//h6[contains(., "Add payment")]')->count());
		self::assertStringContainsString(
			'order_payment_action=incoming',
			$this->client->getCrawler()->selectLink('Add incoming payment')->link()->getUri(),
		);
	}

	public function testDraftOrderPaymentsTabDoesNotShowIncomingPaymentActions(): void
	{
		$this->client->loginUser($this->createUser('order-payment-draft-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-payment-draft-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-payment-draft-' . uniqid())
			->setStatus(OrderStatus::DRAFT)
			->setCustomerNameSnapshot('Draft Customer')
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('0.0000')
			->setPaymentStatus(PaymentStatusEnum::UNPAID);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/payment/order/%d/document', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add incoming payment')->count());
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add outgoing refund')->count());
		self::assertSame(0, $this->client->getCrawler()->filterXPath('//h6[contains(., "Add payment")]')->count());
	}

	public function testCanceledUnpaidOrderPaymentsTabDoesNotShowIncomingPaymentActions(): void
	{
		$this->client->loginUser($this->createUser('order-payment-canceled-unpaid-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-payment-canceled-unpaid-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-payment-canceled-unpaid-' . uniqid())
			->setStatus(OrderStatus::CANCELED)
			->setCustomerNameSnapshot('Canceled Unpaid Customer')
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('0.0000')
			->setPaymentStatus(PaymentStatusEnum::UNPAID);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/payment/order/%d/document', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add incoming payment')->count());
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add outgoing refund')->count());
		self::assertSame(0, $this->client->getCrawler()->filterXPath('//h6[contains(., "Add payment")]')->count());
	}

	public function testCanceledPaidOrderShowsManualRefundReviewWarning(): void
	{
		$this->client->loginUser($this->createUser('order-payment-review-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-payment-review-store-' . uniqid());
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-payment-review-' . uniqid())
			->setStatus(OrderStatus::CANCELED)
			->setCustomerNameSnapshot('Paid Canceled Customer')
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('10000.0000')
			->setPaymentStatus(PaymentStatusEnum::PAID);
		$payment = (new Payment())
			->setStore($store)
			->setOrder($order)
			->setCurrency($store->getBaseCurrency())
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount('10000.0000')
			->setAmountBase('10000.0000')
			->setPaidAt(new DateTimeImmutable('2026-05-30 17:14:00'));
		$order->addPayment($payment);

		$this->entityManager->persist($order);
		$this->entityManager->persist($payment);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('.order-show-card', 'Canceled order still has received payment.');
		self::assertSelectorTextContains('.order-show-card', 'Net paid amount: 10 000.00');

		$this->client->request('GET', sprintf('/admin/store/%d/payment/order/%d/document', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('.document-payment-status', 'Canceled order still has received payment.');
		self::assertSelectorTextContains('.document-payment-status', 'Review refund with accounting before closing the financial process.');
		self::assertSelectorTextContains('.document-payment-status', 'Net paid amount: 10 000.00');
		self::assertSelectorTextNotContains('.document-payment-status', 'This document is fully paid.');
		self::assertSame(0, $this->client->getCrawler()->selectLink('Add incoming payment')->count());
		self::assertSame(1, $this->client->getCrawler()->selectLink('Add outgoing refund')->count());
		self::assertSame(0, $this->client->getCrawler()->filterXPath('//h6[contains(., "Add payment")]')->count());
		self::assertStringContainsString(
			'order_payment_action=refund',
			$this->client->getCrawler()->selectLink('Add outgoing refund')->link()->getUri(),
		);
	}

	public function testOrderQuickStatusActionReturnsHistoryAndPaymentsFragments(): void
	{
		$this->client->loginUser($this->createUser('order-quick-status-fragments-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-quick-status-fragments-store-' . uniqid());
		$product = $this->createProduct($store, 'Quick status fragment product');
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-quick-fragments-' . uniqid())
			->setStatus(OrderStatus::DRAFT)
			->setCustomerNameSnapshot('Quick Fragment Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('0.0000')
			->setPaymentStatus(PaymentStatusEnum::UNPAID);
		$entry = (new OrderEntry())
			->setProduct($product)
			->setProductNameSnapshot($product->getName())
			->setProductCodeSnapshot($product->getCode())
			->setUnitCodeSnapshot($product->getUnit()?->getCode() ?? 'pc')
			->setUnitNameSnapshot($product->getUnit()?->getName() ?? 'Piece')
			->setQuantity('1.0000')
			->setUnitPrice('100.0000')
			->setUnitPriceBase('100.0000')
			->setTotalPrice('100.0000')
			->setTotalPriceBase('100.0000');
		$order->addOrderEntry($entry);

		$this->entityManager->persist($order);
		$this->entityManager->persist($entry);
		$this->entityManager->flush();

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler
			->filter('form[data-action="submit->reload-card#submitQuickAction"]')
			->first()
			->form();

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

		self::assertArrayHasKey('fragments', $data);
		self::assertArrayHasKey('card', $data['fragments']);
		self::assertArrayHasKey('row', $data['fragments']);
		self::assertArrayHasKey('history', $data['fragments']);
		self::assertArrayHasKey('payments', $data['fragments']);
		self::assertStringContainsString('Awaiting stock', $data['fragments']['row']);
		self::assertStringContainsString('order.status_changed', $data['fragments']['history']);
		self::assertStringContainsString('draft', $data['fragments']['history']);
		self::assertStringContainsString('confirmed', $data['fragments']['history']);
		self::assertStringContainsString('Add incoming payment', $data['fragments']['payments']);
		self::assertStringContainsString('Add payment', $data['fragments']['payments']);
		self::assertStringContainsString('data-reload-card-target="paymentsCard"', $data['fragments']['payments']);
	}

	public function testNewOrderBuilderFormCanBeSubmitted(): void
	{
		$this->client->loginUser($this->createUser('order-builder-submit-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-submit-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Save', [
			'order[customer]' => $customer->getId(),
			'order[customerPhone]' => $customer->getPhone(),
			'order[customerName]' => $customer->getName(),
			'order[customerLastName]' => $customer->getLastName(),
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
			'order[deliveryAddress]' => 'Test delivery address',
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/', $store->getId()));
		self::assertNotNull($this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]));
	}

	public function testNewOrderBuilderFormSavesDraftCommentsWithOrder(): void
	{
		$this->client->loginUser($this->createUser('order-builder-comment-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-comment-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customer' => $customer->getId(),
				'customerPhone' => $customer->getPhone(),
				'customerName' => $customer->getName(),
				'customerLastName' => $customer->getLastName(),
				'currency' => $store->getBaseCurrency()?->getCode(),
				'draftComments' => ['Created with the order'],
				'_token' => $token,
			],
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/', $store->getId()));

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);

		self::assertInstanceOf(Order::class, $order);
		self::assertNotNull($this->entityManager->getRepository(OrderComment::class)->findOneBy([
			'order' => $order,
			'body' => 'Created with the order',
		]));
	}

	public function testNewOrderBuilderFormCreatesCustomerWhenCustomerIsNotSelected(): void
	{
		$this->client->loginUser($this->createUser('order-builder-new-customer-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-new-customer-store-' . uniqid());
		$phone = '050 123-45-67';

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Save', [
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
		$discountRule = $this->createProductDiscountRule($store, $product, '10.0000');

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
						'discountMode' => 'rule',
						'discountRule' => $discountRule->getId(),
						'discountPercent' => '10.0000',
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
		self::assertSame('18.0000', $order->getTotalAmount());
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

	public function testRelatedProductsEndpointReturnsRelatedProductsForStore(): void
	{
		$this->client->loginUser($this->createUser('order-builder-related-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-related-store-' . uniqid());
		$table = $this->createProduct($store, 'Related table');
		$chair = $this->createProduct($store, 'Related chair');

		$relation = (new ProductRelation())
			->setProduct($table)
			->setRelatedProduct($chair)
			->setType(ProductRelationTypeEnum::ACCESSORY);
		$this->entityManager->persist($relation);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/related-products?productId=%d',
			$store->getId(),
			$table->getId(),
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		$ids = array_column($data['products'], 'id');

		self::assertContains($chair->getId(), $ids);
		self::assertNotContains($table->getId(), $ids);
	}

	public function testRelatedProductsEndpointReturnsEmptyForProductWithoutRelations(): void
	{
		$this->client->loginUser($this->createUser('order-builder-related-empty-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-related-empty-store-' . uniqid());
		$product = $this->createProduct($store, 'Lonely product');

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/related-products?productId=%d',
			$store->getId(),
			$product->getId(),
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame([], $data['products']);
	}

	public function testProductSearchUsesRequestedOrderCurrencyForPrice(): void
	{
		$this->client->loginUser($this->createUser('order-builder-currency-search-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-currency-search-store-' . uniqid());
		$orderCurrency = $this->createCurrencyWithCode('D' . substr(uniqid(), -2), 'Document currency');
		$product = $this->createProduct($store, 'Currency search product');
		$product->setBaseSalePrice('410.0000');
		$this->createExchangeRate($orderCurrency, $store->getBaseCurrency(), $store, '41.00000000');
		$this->entityManager->flush();

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Currency%%20search&currency=%s',
			$store->getId(),
			$orderCurrency->getCode(),
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertCount(1, $data['products']);
		self::assertSame($product->getId(), $data['products'][0]['id']);
		self::assertSame('10.0000', $data['products'][0]['price']);
	}

	public function testProductSearchKeepsProductWithoutResolvablePriceAvailableForManualPricing(): void
	{
		$this->client->loginUser($this->createUser('order-builder-no-price-search-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-no-price-search-store-' . uniqid());
		$product = $this->createProduct($store, 'Manual price product');
		$product->setBaseSalePrice(null);
		$this->entityManager->flush();

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Manual%%20price',
			$store->getId(),
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertCount(1, $data['products']);
		self::assertSame($product->getId(), $data['products'][0]['id']);
		self::assertNull($data['products'][0]['price']);
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

	public function testProductSearchShowsWarehouseBatchesAsSeparateOldestFirstOptions(): void
	{
		$this->client->loginUser($this->createUser('order-builder-batch-options-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-batch-options-store-' . uniqid());
		$product = $this->createProduct($store, 'Batch option product');
		$warehouse = $this->createWarehouse($store);
		$warehouseStock = $this->createWarehouseStock($warehouse, $product, '5.0000');
		$olderBatch = $this->createWarehouseStockBatch($warehouseStock, '2.0000', '11.0000', '2026-01-01 00:00:00');
		$newerBatch = $this->createWarehouseStockBatch($warehouseStock, '3.0000', '12.0000', '2026-01-02 00:00:00');

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Batch%%20option',
			$store->getId(),
		));

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertCount(1, $data['products']);
		self::assertCount(2, $data['products'][0]['stockOptions']);
		self::assertSame($olderBatch->getId(), $data['products'][0]['stockOptions'][0]['batchId']);
		self::assertSame('2.0000', $data['products'][0]['stockOptions'][0]['available']);
		self::assertSame('11.0000', $data['products'][0]['stockOptions'][0]['price']);
		self::assertSame($newerBatch->getId(), $data['products'][0]['stockOptions'][1]['batchId']);
		self::assertSame('3.0000', $data['products'][0]['stockOptions'][1]['available']);
		self::assertSame('12.0000', $data['products'][0]['stockOptions'][1]['price']);

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Batch%%20option&excludedOptions[]=stock:%d:%d:%d',
			$store->getId(),
			$product->getId(),
			$warehouse->getId(),
			$olderBatch->getId(),
		));

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertCount(1, $data['products']);
		self::assertCount(1, $data['products'][0]['stockOptions']);
		self::assertSame($newerBatch->getId(), $data['products'][0]['stockOptions'][0]['batchId']);
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
		$version = $crawler->filter('input[name="order[version]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()), [
			'order' => [
				'version' => $version,
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

	public function testEditPageShowsCommentsAndHistory(): void
	{
		$user = $this->createUser('order-builder-edit-history-admin-' . uniqid() . '@example.com');
		$this->client->loginUser($user);
		$store = $this->createStore('order-builder-edit-history-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Save', [
			'order[customer]' => $customer->getId(),
			'order[customerPhone]' => $customer->getPhone(),
			'order[customerName]' => $customer->getName(),
			'order[customerLastName]' => $customer->getLastName(),
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
		]);

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);
		self::assertInstanceOf(Order::class, $order);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()));
		$commentForm = $crawler->selectButton('Add comment')->form([
			'body' => 'Visible on edit page',
		]);
		$this->client->submit($commentForm);
		self::assertResponseRedirects(sprintf('/admin/store/%d/order/?id=%d&page=1', $store->getId(), $order->getId()));

		$this->client->request('GET', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Visible on edit page');
		self::assertSelectorTextContains('body', 'History');
		self::assertSelectorTextContains('body', 'Order created');
	}

	public function testCommentsCardOnIndexPageIsPreparedForAjaxRefresh(): void
	{
		$user = $this->createUser('order-builder-index-comments-admin-' . uniqid() . '@example.com');
		$this->client->loginUser($user);
		$store = $this->createStore('order-builder-index-comments-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Save', [
			'order[customer]' => $customer->getId(),
			'order[customerPhone]' => $customer->getPhone(),
			'order[customerName]' => $customer->getName(),
			'order[customerLastName]' => $customer->getLastName(),
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
		]);

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);
		self::assertInstanceOf(Order::class, $order);

		$this->client->request('GET', sprintf('/admin/store/%d/order/%d/comments', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('[data-controller~="order-discussion"]');
		self::assertSelectorExists('[data-order-discussion-refresh-comments-url-value]');
		self::assertSelectorExists('[data-order-discussion-refresh-history-url-value]');
	}

	public function testEditPageAddsCommentWithoutRedirectForXmlHttpRequest(): void
	{
		$user = $this->createUser('order-builder-edit-ajax-comment-admin-' . uniqid() . '@example.com');
		$this->client->loginUser($user);
		$store = $this->createStore('order-builder-edit-ajax-comment-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Save', [
			'order[customer]' => $customer->getId(),
			'order[customerPhone]' => $customer->getPhone(),
			'order[customerName]' => $customer->getName(),
			'order[customerLastName]' => $customer->getLastName(),
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
		]);

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);
		self::assertInstanceOf(Order::class, $order);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()));
		$commentForm = $crawler->selectButton('Add comment')->form([
			'body' => 'Added without page reload',
		]);

		$this->client->request(
			$commentForm->getMethod(),
			$commentForm->getUri(),
			$commentForm->getValues(),
			[],
			['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
		);

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Added without page reload');
		self::assertSelectorExists('[data-controller~="order-discussion"]');
		self::assertSelectorTextContains('body', 'order.comment_added');
	}

	public function testEditPageEditsAndDeletesCommentWithoutRedirectForXmlHttpRequest(): void
	{
		$user = $this->createUser('order-builder-edit-ajax-update-comment-admin-' . uniqid() . '@example.com');
		$this->client->loginUser($user);
		$store = $this->createStore('order-builder-edit-ajax-update-comment-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submitForm('Save', [
			'order[customer]' => $customer->getId(),
			'order[customerPhone]' => $customer->getPhone(),
			'order[customerName]' => $customer->getName(),
			'order[customerLastName]' => $customer->getLastName(),
			'order[currency]' => $store->getBaseCurrency()?->getCode(),
		]);

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);
		self::assertInstanceOf(Order::class, $order);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()));
		$commentForm = $crawler->selectButton('Add comment')->form([
			'body' => 'Comment before edit',
		]);
		$crawler = $this->client->request(
			$commentForm->getMethod(),
			$commentForm->getUri(),
			$commentForm->getValues(),
			[],
			['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
		);

		$editForm = $crawler->filter('[data-order-discussion-target~="editForm"]')->form([
			'body' => 'Comment after edit',
		]);
		$crawler = $this->client->request(
			$editForm->getMethod(),
			$editForm->getUri(),
			$editForm->getValues(),
			[],
			['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
		);

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Comment after edit');
		self::assertSelectorTextContains('body', 'order.comment_edited');

		$deleteForm = $crawler->filter('form[data-confirm="Delete comment?"]')->form();
		$this->client->request(
			$deleteForm->getMethod(),
			$deleteForm->getUri(),
			$deleteForm->getValues(),
			[],
			['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
		);

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'No comments.');
		self::assertSelectorNotExists('[data-order-discussion-comment]');
		self::assertSelectorTextContains('body', 'order.comment_deleted');
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
		return $this->createCurrencyWithCode('UAH', 'Ukrainian hryvnia');
	}

	private function createCurrencyWithCode(string $code, string $name): Currency
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

	private function createExchangeRate(Currency $fromCurrency, ?Currency $toCurrency, Store $store, string $rate): ExchangeRate
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

	private function createProductDiscountRule(Store $store, Product $product, string $percent): ProductDiscountRule
	{
		$rule = (new ProductDiscountRule())
			->setStore($store)
			->setName('Order discount ' . uniqid())
			->setPercent($percent)
			->setIsDefault(true);
		$rule->addTarget((new ProductDiscountTarget())
			->setTargetType(ProductDiscountTargetTypeEnum::PRODUCT)
			->setProduct($product));

		$this->entityManager->persist($rule);
		$this->entityManager->flush();

		return $rule;
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

	private function createWarehouseStockBatch(WarehouseStock $warehouseStock, string $quantity, string $salePrice, string $receivedAt): WarehouseStockBatch
	{
		$batch = (new WarehouseStockBatch())
			->setWarehouseStock($warehouseStock)
			->setInitialQuantity($quantity)
			->setRemainingQuantity($quantity)
			->setUnitCost('1.0000')
			->setSalePrice($salePrice)
			->setReceivedAt(new DateTimeImmutable($receivedAt));
		$warehouseStock->addWarehouseStockBatch($batch);

		$this->entityManager->persist($batch);
		$this->entityManager->flush();

		return $batch;
	}
}

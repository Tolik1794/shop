<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\ExchangeRate;
use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\OrderEntry;
use App\Entity\OrderEntryFulfillmentSource;
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
use App\Enum\CommentTypeEnum;
use App\Enum\OrderDiscountModeEnum;
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
use Symfony\Component\HttpFoundation\Response;

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
		self::assertSelectorTextContains('.navbar-page-header__title', 'New order');
		self::assertSelectorTextContains('.navbar-page-header__breadcrumb', 'Orders');
		self::assertSelectorTextContains('.navbar-page-header__breadcrumb', 'New');
		self::assertSelectorExists('[data-controller~="order-form"]');
		self::assertSelectorExists('[data-controller~="draft-order-comments"]');
		self::assertSelectorExists('[data-controller~="draft-order-comments"] .order-discussion-scroll-frame');
		self::assertSelectorExists('[data-controller~="draft-order-comments"] [data-draft-order-comments-target~="newType"]');
		self::assertSelectorExists('[data-controller~="draft-order-comments"] [data-draft-order-comments-target~="newImportant"]');
		self::assertSelectorExists('[data-controller~="draft-order-comments"] .order-comment-template-chip');
		self::assertSelectorNotExists('.order-discussion-scroll-frame--compact');
		self::assertSelectorExists('[data-order-form-target="prototype"]');
		self::assertSelectorExists('.order-builder > .container-fluid > .form-actions');
		self::assertSelectorCount(2, '.form-actions button[type="submit"][form="order-form-new"]');
		self::assertSelectorExists('.form-actions button[name="save"][value="stay"][form="order-form-new"]');
		self::assertSelectorTextContains('.form-actions', 'Save');
		self::assertSelectorTextContains('.form-actions', 'Save and continue');
		self::assertSelectorTextContains('.form-actions', 'Cancel');
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
		self::assertSelectorExists('.order-quick-filters');
		self::assertSelectorTextContains('.order-quick-filters', 'All');
		self::assertSelectorTextContains('.order-quick-filters', 'Drafts');
		self::assertSelectorTextContains('.order-quick-filters', 'Unpaid');
		self::assertSelectorExists('.order-filter-chips');
		self::assertSelectorNotExists('.tab-search-link');
	}

	public function testOrderIndexQuickUnpaidFilterIncludesUnpaidAndPartiallyPaidOrders(): void
	{
		$this->client->loginUser($this->createUser('order-index-quick-unpaid-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-index-quick-unpaid-store-' . uniqid());
		$unpaidOrder = $this->createOrder(
			$store,
			'SO unpaid quick ' . uniqid(),
			OrderStatus::CONFIRMED,
			PaymentStatusEnum::UNPAID,
		);
		$partiallyPaidOrder = $this->createOrder(
			$store,
			'SO partially paid quick ' . uniqid(),
			OrderStatus::CONFIRMED,
			PaymentStatusEnum::PARTIALLY_PAID,
		);
		$paidOrder = $this->createOrder(
			$store,
			'SO paid quick ' . uniqid(),
			OrderStatus::COMPLETED,
			PaymentStatusEnum::PAID,
		);

		$this->client->request('GET', sprintf('/admin/store/%d/order/?order_filter%%5Bquick%%5D=unpaid', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.order-quick-filter-chip.is-active');
		self::assertSelectorTextContains('.order-quick-filter-chip.is-active', 'Unpaid');
		self::assertSelectorTextContains('body', $unpaidOrder->getNumber());
		self::assertSelectorTextContains('body', $partiallyPaidOrder->getNumber());
		self::assertStringNotContainsString($paidOrder->getNumber(), $this->client->getResponse()->getContent());
	}

	public function testOrderIndexNumberSearchIgnoresCaseAndExtraSpaces(): void
	{
		$this->client->loginUser($this->createUser('order-index-number-search-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-index-number-search-store-' . uniqid());
		$matchingOrder = $this->createOrder(
			$store,
			'SO Search Match ' . uniqid(),
			OrderStatus::CONFIRMED,
			PaymentStatusEnum::UNPAID,
		);
		$otherOrder = $this->createOrder(
			$store,
			'SO Other Number ' . uniqid(),
			OrderStatus::CONFIRMED,
			PaymentStatusEnum::UNPAID,
		);

		$this->client->request('GET', sprintf('/admin/store/%d/order/?order_filter%%5Bnumber%%5D=%%20so%%20%%20%%20search%%20match%%20', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', $matchingOrder->getNumber());
		self::assertStringNotContainsString($otherOrder->getNumber(), $this->client->getResponse()->getContent());
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
		self::assertSelectorExists(sprintf('tr[id="%d"][data-breadcrumb-label="%s"]', $firstOrder->getId(), $firstOrder->getNumber()));
		self::assertSelectorTextContains('#admin-navbar-breadcrumb', $firstOrder->getNumber());
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
		self::assertSelectorExists('.order-show-summary');
		self::assertSelectorTextContains('.order-show-summary', $order->getNumber());
		self::assertSelectorTextContains('.order-show-summary', 'Marina Kurceva');
		self::assertSelectorTextContains('.order-show-summary', '+380956554307');
		self::assertSelectorTextContains('.order-show-summary', 'Draft');
		self::assertSelectorTextContains('.order-show-summary', 'Unpaid');
		self::assertSelectorExists('.order-show-summary__item--identity .order-copy-action[aria-label="Copy order number"]');
		self::assertSelectorExists('.order-show-summary__copyable .order-copy-action[data-copy-text="+380956554307"]');
		self::assertSelectorExists('.order-show-summary__item--statuses.order-show-summary__item--wide .order-show-summary__statuses');
		self::assertSelectorExists('.order-show-grid');
		self::assertSelectorTextContains('.order-show-card', 'Customer details');
		self::assertSelectorTextContains('.order-show-card', 'Payment');
		self::assertSelectorTextContains('.order-show-card', 'Delivery');
		self::assertSelectorTextContains('.order-show-card', 'Products');
		self::assertSelectorTextContains('.order-show-card', 'Inventory documents');
		self::assertSelectorTextContains('.order-show-card', 'Not specified');
		self::assertSelectorTextContains('.order-show-payment-total--due', '10 000.00 UAH');
		self::assertSelectorTextContains('.order-show-payment-total--paid', '0.00 UAH');
		self::assertSelectorTextContains('.order-show-product-card', 'Show card desk');
		self::assertSelectorTextContains('.order-show-product-card', '1 pc');
		self::assertSelectorTextContains('.order-show-product-card', '10 000.00 UAH');
		self::assertSelectorExists('.order-show-product-card .order-copy-action[data-copy-feedback="Copied"]');
		self::assertSelectorExists('.order-show-product-card [data-copy-feedback][aria-live="polite"]');
		self::assertSelectorExists('.order-show-product-action[aria-label="Open product"]');
	}

	public function testOrderShowPaymentSummaryShowsRemainingDueAndPaidAmounts(): void
	{
		$this->client->loginUser($this->createUser('order-show-payment-summary-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-show-payment-summary-store-' . uniqid());
		$partiallyPaidOrder = $this->createOrder(
			$store,
			'SO-payment-summary-partial-' . uniqid(),
			OrderStatus::CONFIRMED,
			PaymentStatusEnum::PARTIALLY_PAID,
		)
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('2500.0000');

		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $partiallyPaidOrder->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('.order-show-payment-total--due', '7 500.00 UAH');
		self::assertSelectorTextContains('.order-show-payment-total--paid', '2 500.00 UAH');
		self::assertSelectorTextContains('.order-show-payment-meta', '7 500.00');
		self::assertSelectorTextContains('.order-show-payment-meta', '2 500.00');
	}

	public function testOrderShowPaidPaymentSummaryShowsZeroDue(): void
	{
		$this->client->loginUser($this->createUser('order-show-payment-paid-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-show-payment-paid-store-' . uniqid());
		$paidOrder = $this->createOrder(
			$store,
			'SO-payment-summary-paid-' . uniqid(),
			OrderStatus::COMPLETED,
			PaymentStatusEnum::PAID,
		)
			->setTotalAmount('10000.0000')
			->setTotalAmountBase('10000.0000')
			->setPaidAmountBase('10000.0000');

		$this->entityManager->flush();

		$this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $paidOrder->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('.order-show-payment-total--due', '0.00 UAH');
		self::assertSelectorTextContains('.order-show-payment-total--paid', '10 000.00 UAH');
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

	public function testConfirmProductionOrderWithoutDefaultRecipeReturnsUserSafeQuickActionError(): void
	{
		$this->client->loginUser($this->createUser('order-confirm-production-recipe-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-confirm-production-recipe-store-' . uniqid());
		$product = $this->createProduct($store, 'Production product without recipe', true);
		$warehouse = $this->createWarehouse($store);
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-production-recipe-' . uniqid())
			->setStatus(OrderStatus::DRAFT)
			->setCustomerNameSnapshot('Production Recipe Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('0.0000')
			->setPaymentStatus(PaymentStatusEnum::UNPAID);
		$entry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setFulfillmentSource(OrderEntryFulfillmentSource::PRODUCTION)
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
		$form = $crawler->filter('form[action$="/confirm"]')->form();

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

		self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame(
			'Product "Production product without recipe" requires an active default production recipe.',
			$data['flashes'][0]['message'] ?? null,
		);
		self::assertArrayHasKey('card', $data['fragments']);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler->filter('form[action$="/confirm"]')->form();
		$this->client->request($form->getMethod(), $form->getUri(), $form->getValues());
		self::assertResponseRedirects();

		$reloadedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
		self::assertInstanceOf(Order::class, $reloadedOrder);
		self::assertSame(OrderStatus::DRAFT, $reloadedOrder->getStatus());
	}

	public function testNewOrderBuilderFormCanBeSubmitted(): void
	{
		$this->client->loginUser($this->createUser('order-builder-submit-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-submit-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submit($crawler->filter('#order-form-new')->form(), [
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
				'draftComments' => [[
					'body' => 'Created with the order',
					'type' => 'warehouse',
					'important' => '1',
				]],
				'_token' => $token,
			],
		]);

		self::assertResponseRedirects(sprintf('/admin/store/%d/order/', $store->getId()));

		$order = $this->entityManager->getRepository(Order::class)->findOneBy([
			'store' => $store,
			'customer' => $customer,
		]);

		self::assertInstanceOf(Order::class, $order);
		$comment = $this->entityManager->getRepository(OrderComment::class)->findOneBy([
			'order' => $order,
			'body' => 'Created with the order',
		]);
		self::assertInstanceOf(OrderComment::class, $comment);
		self::assertSame(CommentTypeEnum::WAREHOUSE, $comment->getType());
		self::assertTrue($comment->isImportant());
	}

	public function testNewOrderBuilderRendersStructuredDraftCommentAfterInvalidSubmit(): void
	{
		$this->client->loginUser($this->createUser('order-builder-invalid-draft-comment-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-invalid-draft-comment-store-' . uniqid());

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$token = $crawler->filter('input[name="order[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/order/new', $store->getId()), [
			'order' => [
				'customerPhone' => '',
				'customerName' => '',
				'customerLastName' => '',
				'currency' => $store->getBaseCurrency()?->getCode(),
				'draftComments' => [[
					'body' => 'Draft survives invalid submit',
					'type' => 'accounting',
					'important' => '1',
				]],
				'_token' => $token,
			],
		]);

		self::assertResponseStatusCodeSame(422);
		self::assertSelectorTextContains('[data-draft-order-comments-item]', 'Draft survives invalid submit');
		self::assertSelectorTextContains('[data-draft-order-comments-item] [data-draft-order-comments-meta]', 'Accounting');
		self::assertSelectorTextContains('[data-draft-order-comments-item] [data-draft-order-comments-meta]', 'Important');
		self::assertSelectorExists('[data-draft-order-comments-field-name="body"][value="Draft survives invalid submit"]');
		self::assertSelectorExists('[data-draft-order-comments-field-name="type"][value="accounting"]');
		self::assertSelectorExists('[data-draft-order-comments-field-name="important"][value="1"]');
	}

	public function testNewOrderBuilderFormCreatesCustomerWhenCustomerIsNotSelected(): void
	{
		$this->client->loginUser($this->createUser('order-builder-new-customer-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-new-customer-store-' . uniqid());
		$phone = '050 123-45-67';

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submit($crawler->filter('#order-form-new')->form(), [
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

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/%d/edit', $store->getId(), $order->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorCount(2, '[data-order-form-target~="entries"] [data-order-entry-target~="discountRule"] option');
		self::assertSelectorTextContains('[data-order-form-target~="entries"] [data-order-entry-target~="discountRule"]', 'No discount');
		self::assertSelectorTextContains('[data-order-form-target~="entries"] [data-order-entry-target~="discountRule"]', $discountRule->getName());
		self::assertSelectorTextContains('[data-order-form-target~="entries"] .order-entry-head__avail', (string) $product->getUnit()?->getCode());
		self::assertSelectorExists('[data-order-form-target~="entries"] .order-entry-discount.col-md-4');
		self::assertSelectorExists('[data-order-form-target~="entries"] .order-entry-discount > .order-entry-discount-select .form-select.form-select-sm');
		self::assertSelectorExists('[data-order-form-target~="entries"] .order-entry-discount > .order-entry-manual-field .input-group.input-group-sm');
		self::assertSelectorExists('[data-order-form-target~="entries"] .order-entry-total.col-md-4');
	}

	public function testExplicitNoDiscountDoesNotApplyDefaultRule(): void
	{
		$this->client->loginUser($this->createUser('order-builder-no-discount-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-no-discount-store-' . uniqid());
		$customer = $this->createCustomer($store);
		$product = $this->createProduct($store);
		$this->createProductDiscountRule($store, $product, '10.0000');

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
						'discountMode' => 'none',
						'discountRule' => '',
						'discountPercent' => '',
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
		self::assertSame('20.0000', $order->getTotalAmount());
		self::assertNull($order->getDiscountAmount());
		self::assertSame(OrderDiscountModeEnum::NONE, $order->getOrderEntries()->first()->getDiscountMode());
		self::assertNull($order->getOrderEntries()->first()->getDiscountRule());
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

	public function testProductSearchReturnsDefaultDiscountForDiscountedPricePreview(): void
	{
		$this->client->loginUser($this->createUser('order-builder-discount-search-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('order-builder-discount-search-store-' . uniqid());
		$product = $this->createProduct($store, 'Discount search product');
		$discountRule = $this->createProductDiscountRule($store, $product, '10.0000');

		$this->client->request('GET', sprintf(
			'/api/admin/store/%d/order/product-search?q=Discount%%20search',
			$store->getId(),
		));

		self::assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertCount(1, $data['products']);
		self::assertSame('10.0000', $data['products'][0]['price']);
		self::assertSame($discountRule->getId(), $data['products'][0]['defaultDiscountRuleId']);
		self::assertSame('10.0000', $data['products'][0]['discountRules'][0]['percent']);
		self::assertTrue($data['products'][0]['discountRules'][0]['isDefault']);
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
		self::assertSelectorTextContains('.navbar-page-header__title', 'Edit order');
		self::assertSelectorTextContains('.navbar-page-header__subtitle', $order->getNumber());
		self::assertSelectorTextContains('.navbar-page-header__breadcrumb', $order->getNumber());
		self::assertSelectorCount(2, sprintf('.form-actions button[type="submit"][form="order-form-%d"]', $order->getId()));
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

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submit($crawler->filter('#order-form-new')->form(), [
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
		self::assertSelectorNotExists('.order-history-compact__toggle');
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
		self::assertSelectorExists(sprintf('.order-history-compact [data-bs-target="#order-history-%d-details"]', $order->getId()));
		self::assertSelectorExists('.order-history-compact__scroll-frame > .order-history-compact__scroll > .order-history-compact__entry');
		self::assertSelectorExists(sprintf('.order-history-compact__scroll-frame > .order-history-compact__scroll > #order-history-%d-details', $order->getId()));
		self::assertSelectorNotExists(sprintf('#order-history-%d-details .order-history-compact__scroll-frame', $order->getId()));
		self::assertSelectorExists('.order-history-compact__toggle-icon--closed.fa-chevron-down');
		self::assertSelectorExists('.order-history-compact__toggle-icon--open.fa-chevron-up');
		self::assertSelectorNotExists('.order-history-compact a');
	}

	public function testCommentsCardOnIndexPageIsPreparedForAjaxRefresh(): void
	{
		$user = $this->createUser('order-builder-index-comments-admin-' . uniqid() . '@example.com');
		$this->client->loginUser($user);
		$store = $this->createStore('order-builder-index-comments-store-' . uniqid());
		$customer = $this->createCustomer($store);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submit($crawler->filter('#order-form-new')->form(), [
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

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submit($crawler->filter('#order-form-new')->form(), [
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

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/order/new', $store->getId()));
		$this->client->submit($crawler->filter('#order-form-new')->form(), [
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

	private function createOrder(
		Store $store,
		string $number,
		OrderStatus $status = OrderStatus::DRAFT,
		PaymentStatusEnum $paymentStatus = PaymentStatusEnum::UNPAID,
	): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber($number)
			->setStatus($status)
			->setCustomerNameSnapshot('Filter Customer')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase($paymentStatus === PaymentStatusEnum::UNPAID ? '0.0000' : '50.0000')
			->setPaymentStatus($paymentStatus);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		return $order;
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

<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Enum\PaymentStatusEnum;
use App\Enum\ProductKindEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PurchaseIndexControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testPurchaseIndexShowsOrderStyleToolbarAndTabs(): void
	{
		$this->client->loginUser($this->createUser('purchase-index-toolbar-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('purchase-index-toolbar-store-' . uniqid());

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?purchase_filter%%5Bnumber%%5D=PO', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('#purchase-quick-search');
		self::assertSelectorExists('button[data-bs-target="#purchase_filter-advanced-filters"]');
		self::assertSelectorExists('#purchase_filter-advanced-filters form[name="purchase_filter"]');
		self::assertSelectorExists('.order-quick-filters');
		self::assertSelectorTextContains('.order-quick-filters', 'All');
		self::assertSelectorTextContains('.order-quick-filters', 'Drafts');
		self::assertSelectorTextContains('.order-quick-filters', 'Unpaid');
		self::assertSelectorTextContains('.order-quick-filters', 'Returned');
		self::assertSelectorExists('.order-filter-chips');
		self::assertSelectorNotExists('.tab-search-link');
		self::assertStringContainsString('class="sticky-top tab tab-card"', (string) $this->client->getResponse()->getContent());
	}

	public function testPurchaseIndexQuickUnpaidFilterIncludesUnpaidAndPartiallyPaidPurchases(): void
	{
		$this->client->loginUser($this->createUser('purchase-index-quick-unpaid-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('purchase-index-quick-unpaid-store-' . uniqid());
		$unpaidPurchase = $this->createPurchase($store, 'PO-unpaid-' . uniqid(), 'Unpaid Supplier', PurchaseStatus::ORDERED, PaymentStatusEnum::UNPAID);
		$partiallyPaidPurchase = $this->createPurchase($store, 'PO-partial-' . uniqid(), 'Partial Supplier', PurchaseStatus::ORDERED, PaymentStatusEnum::PARTIALLY_PAID);
		$paidPurchase = $this->createPurchase($store, 'PO-complete-' . uniqid(), 'Paid Supplier', PurchaseStatus::COMPLETED, PaymentStatusEnum::PAID);

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?purchase_filter%%5Bquick%%5D=unpaid', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.order-quick-filter-chip.is-active');
		self::assertSelectorTextContains('.order-quick-filter-chip.is-active', 'Unpaid');
		self::assertSelectorTextContains('body', $unpaidPurchase->getNumber());
		self::assertSelectorTextContains('body', $partiallyPaidPurchase->getNumber());
		self::assertStringNotContainsString((string) $paidPurchase->getNumber(), (string) $this->client->getResponse()->getContent());
	}

	public function testPurchaseIndexQuickReturnedFilterIncludesReturnedAndPartiallyReturnedPurchases(): void
	{
		$this->client->loginUser($this->createUser('purchase-index-quick-returned-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('purchase-index-quick-returned-store-' . uniqid());
		$returnedPurchase = $this->createPurchase($store, 'PO-returned-' . uniqid(), 'Returned Supplier', PurchaseStatus::RETURNED);
		$partiallyReturnedPurchase = $this->createPurchase($store, 'PO-partial-return-' . uniqid(), 'Partial Returned Supplier', PurchaseStatus::PARTIALLY_RETURNED);
		$canceledPurchase = $this->createPurchase($store, 'PO-canceled-' . uniqid(), 'Canceled Supplier', PurchaseStatus::CANCELED);

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?purchase_filter%%5Bquick%%5D=returned', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.order-quick-filter-chip.is-active');
		self::assertSelectorTextContains('.order-quick-filter-chip.is-active', 'Returned');
		self::assertSelectorTextContains('body', $returnedPurchase->getNumber());
		self::assertSelectorTextContains('body', $partiallyReturnedPurchase->getNumber());
		self::assertStringNotContainsString((string) $canceledPurchase->getNumber(), (string) $this->client->getResponse()->getContent());
	}

	public function testPurchaseIndexQuickSearchMatchesNumberOrSupplier(): void
	{
		$this->client->loginUser($this->createUser('purchase-index-search-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('purchase-index-search-store-' . uniqid());
		$numberMatch = $this->createPurchase($store, 'PO Search Match ' . uniqid(), 'Number Supplier');
		$supplierMatch = $this->createPurchase($store, 'PO Other ' . uniqid(), 'Acme Materials ' . uniqid());
		$otherPurchase = $this->createPurchase($store, 'PO Hidden ' . uniqid(), 'Hidden Supplier');

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?purchase_filter%%5Bnumber%%5D=%%20po%%20%%20search%%20match%%20', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', $numberMatch->getNumber());
		self::assertStringNotContainsString((string) $supplierMatch->getNumber(), (string) $this->client->getResponse()->getContent());
		self::assertStringNotContainsString((string) $otherPurchase->getNumber(), (string) $this->client->getResponse()->getContent());

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?purchase_filter%%5Bnumber%%5D=acme', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', $supplierMatch->getNumber());
		self::assertStringNotContainsString((string) $numberMatch->getNumber(), (string) $this->client->getResponse()->getContent());
		self::assertStringNotContainsString((string) $otherPurchase->getNumber(), (string) $this->client->getResponse()->getContent());
	}

	public function testPurchaseShowPanelUsesCompactSummaryAndReadableSections(): void
	{
		$this->client->loginUser($this->createUser('purchase-show-polish-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('purchase-show-polish-store-' . uniqid());
		$purchase = $this->createPurchase($store, 'PO-show-polish-' . uniqid(), 'Long Supplier Materials Company');
		$paidPurchase = $this->createPurchase($store, 'PO-show-paid-' . uniqid(), 'Paid Supplier', PurchaseStatus::COMPLETED, PaymentStatusEnum::PAID);

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?id=%d', $store->getId(), $purchase->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorNotExists('.purchase-show-card > .card-header');
		self::assertSelectorExists('.purchase-show-actions form[data-action="submit->reload-card#submitQuickAction"]');
		self::assertSelectorExists('.purchase-show-summary__number + .purchase-show-summary__statuses');
		self::assertSelectorExists('.purchase-show-summary__supplier + .purchase-show-summary__total');
		self::assertSelectorTextContains('.purchase-show-summary', (string) $purchase->getNumber());
		self::assertSelectorTextContains('.purchase-show-summary', 'Long Supplier Materials Company');
		self::assertStringNotContainsString('+380000000001', $this->client->getCrawler()->filter('.purchase-show-summary')->text());
		self::assertSelectorTextContains('.purchase-show-supplier-fields', '+380000000001');
		self::assertSelectorTextContains('.purchase-show-supplier-fields', 'supplier-');
		self::assertSelectorTextContains('.order-show-payment-total--due', '100.00 UAH');
		self::assertSelectorTextContains('.order-show-payment-total--paid', '0.00 UAH');
		self::assertSelectorNotExists('.purchase-show-payment-totals--paid');
		self::assertSelectorTextContains('.order-show-money-list', '100.00 UAH');
		self::assertSelectorTextContains('.order-show-money-list', '0.00 UAH');
		self::assertSelectorTextContains('body', 'Delivery cost');
		self::assertSelectorTextContains('.purchase-show-product-total', '100.00 UAH');
		self::assertSelectorTextContains('.purchase-show-product-sku', 'purchase-product-');

		$this->client->request('GET', sprintf('/admin/store/%d/purchase/?id=%d', $store->getId(), $paidPurchase->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.purchase-show-payment-totals--paid');
		self::assertSelectorTextContains('.order-show-payment-total--due', '0.00 UAH');
		self::assertSelectorTextContains('.order-show-payment-total--paid', '100.00 UAH');
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

	private function createPurchase(
		Store $store,
		string $number,
		string $supplierName,
		PurchaseStatus $status = PurchaseStatus::DRAFT,
		PaymentStatusEnum $paymentStatus = PaymentStatusEnum::UNPAID,
	): Purchase {
		[$warehouse, $product] = $this->createWarehouseAndProduct($store, $number);
		$paidAmountBase = match ($paymentStatus) {
			PaymentStatusEnum::UNPAID => '0.0000',
			PaymentStatusEnum::PARTIALLY_PAID => '25.0000',
			default => '100.0000',
		};
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber($number)
			->setStatus($status)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setSupplierNameSnapshot($supplierName)
			->setSupplierPhoneSnapshot('+380000000001')
			->setSupplierEmailSnapshot('supplier-' . substr(md5($number), 0, 8) . '@example.com')
			->setTotalAmount('100.0000')
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase($paidAmountBase)
			->setPaymentStatus($paymentStatus);
		$entry = (new PurchaseEntry())
			->setPurchase($purchase)
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitCost('50.0000')
			->setUnitCostBase('50.0000')
			->setTotalCost('100.0000')
			->setTotalCostBase('100.0000')
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

	/**
	 * @return array{Warehouse, Product}
	 */
	private function createWarehouseAndProduct(Store $store, string $seed): array
	{
		$suffix = substr(md5($seed), 0, 8);
		$category = (new Category())
			->setStore($store)
			->setName('Purchase category ' . $suffix)
			->setLevel(1);
		$unit = (new Unit())
			->setStore($store)
			->setCode('pc-' . $suffix)
			->setName('Piece')
			->setPrecision(0);
		$warehouse = (new Warehouse())
			->setStore($store)
			->setName('Purchase warehouse ' . $suffix);
		$product = (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($unit)
			->setName('Purchase product ' . $suffix)
			->setCode('purchase-product-' . $suffix)
			->setCanBeSold(true)
			->setCanBePurchased(true)
			->setCanBeManufactured(false)
			->setProductKind(ProductKindEnum::FINISHED_PRODUCT);

		$this->entityManager->persist($category);
		$this->entityManager->persist($unit);
		$this->entityManager->persist($warehouse);
		$this->entityManager->persist($product);
		$this->entityManager->flush();

		return [$warehouse, $product];
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

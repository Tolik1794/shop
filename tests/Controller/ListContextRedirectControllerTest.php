<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ListContextRedirectControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->client, $this->entityManager);
	}

	public function testCustomerEditLinkAndSavePreserveFilteredListContext(): void
	{
		$this->client->loginUser($this->createUser('list-context-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('list-context-store-' . uniqid());
		$customer = $this->createCustomer($store, 'Filtered');

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/customer/?customer_filter%%5Bname%%5D=Filtered&page=1',
			$store->getId(),
		));

		self::assertResponseIsSuccessful();

		$editHref = $crawler->selectLink('Edit')->link()->getUri();
		self::assertStringContainsString('customer_filter%5Bname%5D=Filtered', $editHref);
		self::assertStringContainsString('page=1', $editHref);

		$crawler = $this->client->request('GET', $editHref);
		self::assertResponseIsSuccessful();

		$this->client->submit($crawler->selectButton('Save')->form([
			'customer[name]' => 'Filtered updated',
			'customer[lastName]' => $customer->getLastName(),
			'customer[phone]' => $customer->getPhone(),
			'customer[email]' => $customer->getEmail(),
			'customer[status]' => $customer->getStatus()->value,
			'customer[comment]' => $customer->getComment(),
		]));

		self::assertResponseRedirects();
		self::assertStringContainsString(
			'customer_filter%5Bname%5D=Filtered',
			$this->client->getResponse()->headers->get('Location') ?? '',
		);
		self::assertStringContainsString('page=1', $this->client->getResponse()->headers->get('Location') ?? '');
	}

	public function testPaymentCreatedFromOrderReturnsToFilteredOrderList(): void
	{
		$this->client->loginUser($this->createUser('payment-return-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('payment-return-store-' . uniqid());
		$order = $this->createOrder($store);
		$returnUrl = sprintf(
			'/admin/store/%d/order/?order_filter%%5Bnumber%%5D=%s&page=2&id=%d',
			$store->getId(),
			rawurlencode((string) $order->getNumber()),
			$order->getId(),
		);

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/payment/new?order_id=%d&return_url=%s',
			$store->getId(),
			$order->getId(),
			rawurlencode($returnUrl),
		));

		self::assertResponseIsSuccessful();

		$this->client->submit($crawler->selectButton('Save')->form());

		self::assertResponseRedirects($returnUrl);
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

	private function createCustomer(Store $store, string $name): Customer
	{
		$customer = (new Customer())
			->setName($name)
			->setLastName('Customer')
			->setPhone('+380' . random_int(100000000, 999999999))
			->setEmail('list-context-customer-' . uniqid() . '@example.com')
			->setComment('Context test')
			->setStore($store);

		$this->entityManager->persist($customer);
		$this->entityManager->flush();

		return $customer;
	}

	private function createOrder(Store $store): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setNumber('SO-' . uniqid())
			->setStatus(OrderStatus::CONFIRMED)
			->setTotalAmount('10.0000')
			->setTotalAmountBase('10.0000');

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		return $order;
	}
}

<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StoreDashboardControllerTest extends WebTestCase
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

	public function testStoreDashboardRendersForSuperAdmin(): void
	{
		$this->client->loginUser($this->createUser('dashboard-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('dashboard-controller-' . uniqid());

		$this->client->request('GET', sprintf('/admin/store/%d/main', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('h1', 'Store dashboard');
	}

	public function testStoreDashboardAcceptsEmptyWarehouseFilter(): void
	{
		$this->client->loginUser($this->createUser('dashboard-warehouse-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('dashboard-warehouse-controller-' . uniqid());

		$this->client->request('GET', sprintf('/admin/store/%d/main?warehouse=', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('h1', 'Store dashboard');
	}

	private function createUser(string $email): User
	{
		$user = (new User())
			->setEmail($email)
			->setNickname(str_replace(['@', '.'], '-', $email))
			->setFirstName('Dashboard')
			->setLastName('Admin')
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
}

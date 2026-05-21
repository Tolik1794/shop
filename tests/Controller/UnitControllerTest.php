<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UnitControllerTest extends WebTestCase
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
		$this->client->request('GET', '/admin/store/1/unit/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexIsAvailableForAdminUser(): void
	{
		$this->client->loginUser($this->createUser('unit-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('unit-index-store-' . uniqid());
		$this->createUnit($store, 'box-' . uniqid(), 'Box');

		$this->client->request('GET', sprintf('/admin/store/%d/unit/', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertPageTitleContains('Units');
		self::assertSelectorTextContains('body', 'Box');
	}

	public function testShowDisplaysUnit(): void
	{
		$this->client->loginUser($this->createUser('unit-show-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('unit-show-store-' . uniqid());
		$unit = $this->createUnit($store, 'pack-' . uniqid(), 'Pack');

		$this->client->request('GET', sprintf(
			'/admin/store/%d/unit/%d/show',
			$store->getId(),
			$unit->getId()
		));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Pack');
	}

	public function testShowRejectsUnitFromAnotherStore(): void
	{
		$this->client->loginUser($this->createUser('unit-scope-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('unit-scope-store-' . uniqid());
		$anotherStore = $this->createStore('unit-other-store-' . uniqid());
		$unit = $this->createUnit($anotherStore, 'scope-' . uniqid(), 'Scoped');

		$this->client->request('GET', sprintf(
			'/admin/store/%d/unit/%d/show',
			$store->getId(),
			$unit->getId()
		));

		self::assertResponseStatusCodeSame(404);
	}

	public function testArchiveMarksUnitInactiveAndDeleted(): void
	{
		$this->client->loginUser($this->createUser('unit-archive-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('unit-archive-store-' . uniqid());
		$unit = $this->createUnit($store, 'archive-' . uniqid(), 'Archive');

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/unit/%d/show',
			$store->getId(),
			$unit->getId()
		));
		self::assertResponseIsSuccessful();

		$this->client->submit($crawler->selectButton('Archive')->form());

		self::assertResponseRedirects(sprintf('/admin/store/%d/unit/', $store->getId()));

		$archivedUnit = $this->entityManager->getRepository(Unit::class)->find($unit->getId());

		self::assertInstanceOf(Unit::class, $archivedUnit);
		self::assertSame(ActiveStatusEnum::INACTIVE, $archivedUnit->getStatus());
		self::assertNotNull($archivedUnit->getDeletedAt());
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

	private function createUnit(Store $store, string $code, string $name): Unit
	{
		$unit = (new Unit())
			->setStore($store)
			->setCode($code)
			->setName($name)
			->setPrecision(0);

		$this->entityManager->persist($unit);
		$this->entityManager->flush();

		return $unit;
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

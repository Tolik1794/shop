<?php

namespace App\Tests\Controller;

use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Store;
use App\Entity\Warehouse;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WarehouseControllerTest extends WebTestCase
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
		$this->client->request('GET', '/admin/warehouse/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexIsAvailableForAdminUser(): void
	{
		$this->client->loginUser($this->createUser('warehouse-admin-' . uniqid() . '@example.com'));

		$this->client->request('GET', '/admin/warehouse/');

		self::assertResponseIsSuccessful();
		self::assertPageTitleContains('Warehouse index');
	}

	public function testShowDisplaysWarehouse(): void
	{
		$this->client->loginUser($this->createUser('warehouse-show-admin-' . uniqid() . '@example.com'));
		$warehouse = $this->createWarehouse('Test warehouse ' . uniqid());

		$this->client->request('GET', sprintf('/admin/warehouse/%d', $warehouse->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('h1', 'Warehouse');
		self::assertSelectorTextContains('body', 'Test warehouse');
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

	private function createWarehouse(string $name): Warehouse
	{
		$warehouse = (new Warehouse())
			->setName($name)
			->setStore($this->createStore('warehouse-store-' . uniqid()));

		$this->entityManager->persist($warehouse);
		$this->entityManager->flush();

		return $warehouse;
	}

	private function createStore(string $slug): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com');

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}
}

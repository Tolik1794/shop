<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\InventoryReason;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\InventoryReasonType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InventoryReasonControllerTest extends WebTestCase
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
		$this->client->request('GET', '/admin/store/1/inventory-reason/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexIsAvailableForAdminUser(): void
	{
		$this->client->loginUser($this->createUser('inventory-reason-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('inventory-reason-index-store-' . uniqid());
		$this->createInventoryReason($store, 'Damaged package', InventoryReasonType::DAMAGE);

		$this->client->request('GET', sprintf('/admin/store/%d/inventory-reason/', $store->getId()));

		self::assertResponseIsSuccessful();
		self::assertPageTitleContains('Inventory reasons');
		self::assertSelectorTextContains('body', 'Damaged package');
	}

	public function testShowDisplaysInventoryReason(): void
	{
		$this->client->loginUser($this->createUser('inventory-reason-show-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('inventory-reason-show-store-' . uniqid());
		$inventoryReason = $this->createInventoryReason($store, 'Cycle count', InventoryReasonType::INVENTORY_COUNT);
		$this->createInventoryDocument($store, $inventoryReason, 'ADJ-REASON-' . uniqid());

		$this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-reason/%d/show',
			$store->getId(),
			$inventoryReason->getId()
		));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Cycle count');
		self::assertSelectorTextContains('body', InventoryReasonType::INVENTORY_COUNT->value);
		self::assertSelectorTextContains('body', 'Recent inventory documents');
		self::assertSelectorTextContains('body', 'ADJ-REASON-');
	}

	public function testShowRejectsInventoryReasonFromAnotherStore(): void
	{
		$this->client->loginUser($this->createUser('inventory-reason-scope-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('inventory-reason-scope-store-' . uniqid());
		$anotherStore = $this->createStore('inventory-reason-other-store-' . uniqid());
		$inventoryReason = $this->createInventoryReason($anotherStore, 'Other store reason', InventoryReasonType::OTHER);

		$this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-reason/%d/show',
			$store->getId(),
			$inventoryReason->getId()
		));

		self::assertResponseStatusCodeSame(404);
	}

	public function testArchiveMarksInventoryReasonInactiveAndDeleted(): void
	{
		$this->client->loginUser($this->createUser('inventory-reason-archive-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('inventory-reason-archive-store-' . uniqid());
		$inventoryReason = $this->createInventoryReason($store, 'Archive reason', InventoryReasonType::OTHER);

		$crawler = $this->client->request('GET', sprintf(
			'/admin/store/%d/inventory-reason/%d/show',
			$store->getId(),
			$inventoryReason->getId()
		));
		self::assertResponseIsSuccessful();

		$this->client->submit($crawler->selectButton('Archive')->form());

		self::assertResponseRedirects(sprintf('/admin/store/%d/inventory-reason/', $store->getId()));

		$archivedInventoryReason = $this->entityManager->getRepository(InventoryReason::class)->find($inventoryReason->getId());

		self::assertInstanceOf(InventoryReason::class, $archivedInventoryReason);
		self::assertSame(ActiveStatusEnum::INACTIVE, $archivedInventoryReason->getStatus());
		self::assertNotNull($archivedInventoryReason->getDeletedAt());
	}

	public function testDuplicateTypeAndNameInStoreShowsValidationError(): void
	{
		$this->client->loginUser($this->createUser('inventory-reason-duplicate-admin-' . uniqid() . '@example.com'));
		$store = $this->createStore('inventory-reason-duplicate-store-' . uniqid());
		$this->createInventoryReason($store, 'Duplicate reason', InventoryReasonType::WRITE_OFF);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/inventory-reason/new', $store->getId()));
		self::assertResponseIsSuccessful();

		$form = $crawler->selectButton('Save')->form();
		$form['inventory_reason[name]'] = 'Duplicate reason';
		$form['inventory_reason[type]'] = InventoryReasonType::WRITE_OFF->value;
		$form['inventory_reason[status]'] = ActiveStatusEnum::ACTIVE->value;

		$this->client->submit($form);

		self::assertResponseStatusCodeSame(422);
		self::assertSelectorTextContains('body', 'There is already an inventory reason with this type and name.');
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

	private function createInventoryReason(
		Store $store,
		string $name,
		InventoryReasonType $type
	): InventoryReason {
		$inventoryReason = (new InventoryReason())
			->setStore($store)
			->setName($name)
			->setType($type);

		$this->entityManager->persist($inventoryReason);
		$this->entityManager->flush();

		return $inventoryReason;
	}

	private function createInventoryDocument(Store $store, InventoryReason $reason, string $number): InventoryDocument
	{
		$document = (new InventoryDocument())
			->setStore($store)
			->setNumber($number)
			->setType(InventoryDocumentType::STOCK_ADJUSTMENT)
			->setStatus(InventoryDocumentStatus::DRAFT)
			->setReason($reason);

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
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

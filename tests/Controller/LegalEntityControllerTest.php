<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\LegalEntity;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LegalEntityControllerTest extends WebTestCase
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
		$this->client->request('GET', '/admin/legal-entity/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexIsAvailableForAdminUser(): void
	{
		$this->client->loginUser($this->createUser('legal-entity-admin-' . uniqid() . '@example.com'));
		$this->createLegalEntity('ФОП Тестовий Іван Іванович ' . uniqid(), $taxNumber = $this->uniqueTaxNumber());

		$this->client->request('GET', '/admin/legal-entity/');

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', $taxNumber);
	}

	public function testCreateFopGroup3(): void
	{
		$this->client->loginUser($this->createUser('legal-entity-fop-admin-' . uniqid() . '@example.com'));
		$name = 'ФОП Петренко Петро Петрович ' . uniqid();
		$taxNumber = $this->uniqueTaxNumber();

		$crawler = $this->client->request('GET', '/admin/legal-entity/new');
		self::assertResponseIsSuccessful();

		$form = $crawler->selectButton('Save')->form();
		$form['legal_entity[name]'] = $name;
		$form['legal_entity[shortName]'] = 'ФОП Петренко';
		$form['legal_entity[type]'] = LegalEntityTypeEnum::FOP->value;
		$form['legal_entity[taxNumber]'] = $taxNumber;
		$form['legal_entity[taxSystem]'] = TaxSystemEnum::SIMPLIFIED->value;
		$form['legal_entity[epGroup]'] = '3';
		$form['legal_entity[epRate]'] = '5.00';
		$form['legal_entity[kveds]'] = '47.91, 46.90';
		$form['legal_entity[status]'] = ActiveStatusEnum::ACTIVE->value;

		$this->client->submit($form);

		self::assertResponseRedirects('/admin/legal-entity/');

		$legalEntity = $this->entityManager->getRepository(LegalEntity::class)->findOneBy(['taxNumber' => $taxNumber]);

		self::assertInstanceOf(LegalEntity::class, $legalEntity);
		self::assertSame($name, $legalEntity->getName());
		self::assertSame(LegalEntityTypeEnum::FOP, $legalEntity->getType());
		self::assertSame(TaxSystemEnum::SIMPLIFIED, $legalEntity->getTaxSystem());
		self::assertSame(3, $legalEntity->getEpGroup());
		self::assertSame('5.00', $legalEntity->getEpRate());
		self::assertSame(['47.91', '46.90'], $legalEntity->getKveds());
		self::assertNotNull($legalEntity->getCreatedBy());
	}

	public function testCreateTov(): void
	{
		$this->client->loginUser($this->createUser('legal-entity-tov-admin-' . uniqid() . '@example.com'));
		$name = 'ТОВ Тестова Компанія ' . uniqid();
		$taxNumber = $this->uniqueTaxNumber(8);

		$crawler = $this->client->request('GET', '/admin/legal-entity/new');
		self::assertResponseIsSuccessful();

		$form = $crawler->selectButton('Save')->form();
		$form['legal_entity[name]'] = $name;
		$form['legal_entity[type]'] = LegalEntityTypeEnum::TOV->value;
		$form['legal_entity[taxNumber]'] = $taxNumber;
		$form['legal_entity[taxSystem]'] = TaxSystemEnum::GENERAL->value;
		$form['legal_entity[status]'] = ActiveStatusEnum::ACTIVE->value;

		$this->client->submit($form);

		self::assertResponseRedirects('/admin/legal-entity/');

		$legalEntity = $this->entityManager->getRepository(LegalEntity::class)->findOneBy(['taxNumber' => $taxNumber]);

		self::assertInstanceOf(LegalEntity::class, $legalEntity);
		self::assertSame(LegalEntityTypeEnum::TOV, $legalEntity->getType());
		self::assertSame(TaxSystemEnum::GENERAL, $legalEntity->getTaxSystem());
		self::assertNull($legalEntity->getEpGroup());
	}

	public function testEditUpdatesLegalEntity(): void
	{
		$this->client->loginUser($this->createUser('legal-entity-edit-admin-' . uniqid() . '@example.com'));
		$legalEntity = $this->createLegalEntity('ФОП Редагований ' . uniqid(), $this->uniqueTaxNumber());

		$crawler = $this->client->request('GET', sprintf('/admin/legal-entity/%d/edit', $legalEntity->getId()));
		self::assertResponseIsSuccessful();

		$form = $crawler->selectButton('Save')->form();
		$form['legal_entity[shortName]'] = 'Оновлена коротка назва';

		$this->client->submit($form);

		self::assertResponseStatusCodeSame(303);
		self::assertStringStartsWith('/admin/legal-entity/', (string) $this->client->getResponse()->headers->get('Location'));

		$this->entityManager->clear();
		$updated = $this->entityManager->getRepository(LegalEntity::class)->find($legalEntity->getId());

		self::assertInstanceOf(LegalEntity::class, $updated);
		self::assertSame('Оновлена коротка назва', $updated->getShortName());
		self::assertNotNull($updated->getUpdatedBy());
	}

	public function testDeleteSoftDeletesLegalEntity(): void
	{
		$this->client->loginUser($this->createUser('legal-entity-delete-admin-' . uniqid() . '@example.com'));
		$legalEntity = $this->createLegalEntity('ФОП Видалений ' . uniqid(), $taxNumber = $this->uniqueTaxNumber());

		$crawler = $this->client->request('GET', '/admin/legal-entity/');
		self::assertResponseIsSuccessful();

		$deleteForm = $crawler->filter(sprintf('form[action$="/admin/legal-entity/%d/delete"]', $legalEntity->getId()))->form();
		$this->client->submit($deleteForm);

		self::assertResponseRedirects('/admin/legal-entity/');

		$this->entityManager->clear();
		$deleted = $this->entityManager->getRepository(LegalEntity::class)->find($legalEntity->getId());

		self::assertInstanceOf(LegalEntity::class, $deleted);
		self::assertSame(ActiveStatusEnum::INACTIVE, $deleted->getStatus());
		self::assertNotNull($deleted->getDeletedAt());

		$this->client->request('GET', '/admin/legal-entity/');
		self::assertSelectorTextNotContains('body', $taxNumber);
	}

	public function testStoreCanBeLinkedToLegalEntity(): void
	{
		$legalEntity = $this->createLegalEntity('ФОП Власник Магазину ' . uniqid(), $this->uniqueTaxNumber());
		$store = $this->createStore('legal-entity-store-' . uniqid());

		$store->setLegalEntity($legalEntity);
		$this->entityManager->flush();
		$this->entityManager->clear();

		$reloaded = $this->entityManager->getRepository(Store::class)->find($store->getId());

		self::assertInstanceOf(Store::class, $reloaded);
		self::assertNotNull($reloaded->getLegalEntity());
		self::assertSame($legalEntity->getId(), $reloaded->getLegalEntity()->getId());
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

	private function createLegalEntity(string $name, string $taxNumber): LegalEntity
	{
		$legalEntity = (new LegalEntity())
			->setName($name)
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber($taxNumber)
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup(3)
			->setEpRate('5.00');

		$this->entityManager->persist($legalEntity);
		$this->entityManager->flush();

		return $legalEntity;
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

	private function uniqueTaxNumber(int $length = 10): string
	{
		return substr((string) random_int(1000000000, 9999999999), 0, $length);
	}
}

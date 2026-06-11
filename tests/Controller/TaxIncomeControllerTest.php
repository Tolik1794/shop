<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\IncomeRecordHistory;
use App\Entity\LegalEntity;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaxIncomeControllerTest extends WebTestCase
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
		$this->client->request('GET', '/admin/tax/income/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexShowsRecordsAndTotalsFilteredByLegalEntity(): void
	{
		$this->client->loginUser($this->createUser('tax-income-admin-' . uniqid() . '@example.com'));
		$legalEntity = $this->persistLegalEntity();
		$counterparty = 'Counterparty-' . uniqid();
		$this->persistIncomeRecord($legalEntity, IncomeClassificationEnum::INCOME, '1000.0000', $counterparty);
		$this->persistIncomeRecord($legalEntity, IncomeClassificationEnum::REFUND, '200.0000');

		$this->client->request('GET', '/admin/tax/income/', [
			'income_record_filter' => ['legalEntity' => $legalEntity->getId()],
		]);

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', $counterparty);
		self::assertSelectorTextContains('tfoot', '1 000.00');
		self::assertSelectorTextContains('tfoot', '800.00');
	}

	public function testManualRecordCreation(): void
	{
		$this->client->loginUser($this->createUser('tax-income-manual-' . uniqid() . '@example.com'));
		$legalEntity = $this->persistLegalEntity();
		$this->persistCurrency('UAH');

		$crawler = $this->client->request('GET', '/admin/tax/income/new');
		self::assertResponseIsSuccessful();

		$form = $crawler->selectButton('Save')->form();
		$form['income_record[legalEntity]'] = (string) $legalEntity->getId();
		$form['income_record[recognizedAt]'] = '2026-05-05';
		$form['income_record[amount]'] = '500.0000';
		$form['income_record[currency]'] = 'UAH';
		$form['income_record[classification]'] = IncomeClassificationEnum::INCOME->value;
		$form['income_record[counterparty]'] = 'Готівковий продаж';

		$this->client->submit($form);

		self::assertResponseRedirects('/admin/tax/income/');

		$record = $this->entityManager->getRepository(IncomeRecord::class)->findOneBy([
			'legalEntity' => $legalEntity,
			'sourceType' => IncomeSourceTypeEnum::MANUAL,
		]);

		self::assertInstanceOf(IncomeRecord::class, $record);
		self::assertSame('500.0000', $record->getAmountUah());
		self::assertSame('1.00000000', $record->getNbuExchangeRate());
		self::assertNotNull($record->getCreatedBy());
	}

	public function testReclassifyChangesClassificationAndWritesHistory(): void
	{
		$this->client->loginUser($this->createUser('tax-income-reclass-' . uniqid() . '@example.com'));
		$legalEntity = $this->persistLegalEntity();
		$record = $this->persistIncomeRecord($legalEntity, IncomeClassificationEnum::INCOME, '300.0000');

		$crawler = $this->client->request('GET', sprintf('/admin/tax/income/%d/reclassify', $record->getId()));
		self::assertResponseIsSuccessful();

		$form = $crawler->selectButton('Save')->form();
		$form['income_reclassify[classification]'] = IncomeClassificationEnum::NON_INCOME_OWN_FUNDS->value;
		$form['income_reclassify[comment]'] = 'Поповнення власних коштів';

		$this->client->submit($form);

		self::assertResponseRedirects('/admin/tax/income/');

		$this->entityManager->clear();
		$updated = $this->entityManager->getRepository(IncomeRecord::class)->find($record->getId());

		self::assertSame(IncomeClassificationEnum::NON_INCOME_OWN_FUNDS, $updated?->getClassification());

		$history = $this->entityManager->getRepository(IncomeRecordHistory::class)->findOneBy([
			'incomeRecord' => $updated,
			'eventKey' => 'income.reclassified',
		]);
		self::assertInstanceOf(IncomeRecordHistory::class, $history);
		self::assertSame('income', $history->getPayload()['old_classification'] ?? null);
		self::assertSame('non_income_own_funds', $history->getPayload()['new_classification'] ?? null);
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

	private function persistLegalEntity(): LegalEntity
	{
		$legalEntity = (new LegalEntity())
			->setName('ФОП Дохідний ' . uniqid())
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber(substr((string) random_int(1000000000, 9999999999), 0, 10))
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup(3)
			->setEpRate('5.00');

		$this->entityManager->persist($legalEntity);
		$this->entityManager->flush();

		return $legalEntity;
	}

	private function persistIncomeRecord(
		LegalEntity $legalEntity,
		IncomeClassificationEnum $classification,
		string $amountUah,
		?string $counterparty = null,
	): IncomeRecord {
		$record = (new IncomeRecord())
			->setLegalEntity($legalEntity)
			->setRecognizedAt(new DateTimeImmutable('2026-02-10'))
			->setAmount($amountUah)
			->setCurrency($this->persistCurrency('UAH'))
			->setAmountUah($amountUah)
			->setNbuExchangeRate('1.00000000')
			->setSourceType(IncomeSourceTypeEnum::MANUAL)
			->setClassification($classification)
			->setCounterparty($counterparty);

		$this->entityManager->persist($record);
		$this->entityManager->flush();

		return $record;
	}

	private function persistCurrency(string $code): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($code)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}
}

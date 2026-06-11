<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
use App\Entity\TaxReportDraft;
use App\Entity\TaxReportingPeriod;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxPeriodStatusEnum;
use App\Enum\TaxPeriodTypeEnum;
use App\Enum\TaxSystemEnum;
use App\Manager\IncomeRecordManager;
use App\Service\Tax\TaxReportGeneratorService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaxReportControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testIndexRequiresAuthentication(): void
	{
		$this->client->request('GET', '/admin/tax/reports/');

		self::assertResponseRedirects('/login');
	}

	public function testGenerateDraftViaForm(): void
	{
		$this->client->loginUser($this->createUser('tax-report-gen-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$crawler = $this->client->request('GET', sprintf('/admin/tax/reports/?entity=%d&year=2026', $entity->getId()));
		self::assertResponseIsSuccessful();

		$form = $crawler->filter('form[action$="/admin/tax/reports/generate"]')->form();
		$form['quarter'] = '1';

		$this->client->submit($form);

		$draft = $this->entityManager->getRepository(TaxReportDraft::class)->findOneBy(['legalEntity' => $entity]);
		self::assertInstanceOf(TaxReportDraft::class, $draft);
		self::assertResponseRedirects(sprintf('/admin/tax/reports/%d', $draft->getId()));
	}

	public function testShowDisplaysFieldsAndManualCorrectionPersists(): void
	{
		$this->client->loginUser($this->createUser('tax-report-show-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$uah = $this->persistCurrency('UAH');
		$entity = $this->persistLegalEntity(3, '5.00');
		$this->persistIncomeRecord($entity, $uah, '10000.0000', IncomeClassificationEnum::INCOME, '2026-02-01');

		$generator = static::getContainer()->get(TaxReportGeneratorService::class);
		$draft = $generator->generate($entity, 2026, 1);

		$crawler = $this->client->request('GET', sprintf('/admin/tax/reports/%d', $draft->getId()));
		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', '10000.0000');

		$form = $crawler->filter(sprintf('form[action$="/admin/tax/reports/%d/update"]', $draft->getId()))->form();
		$form['fields[income_cumulative]'] = '10500.0000';

		$this->client->submit($form);
		self::assertResponseRedirects(sprintf('/admin/tax/reports/%d', $draft->getId()));

		$this->entityManager->clear();
		$updated = $this->entityManager->getRepository(TaxReportDraft::class)->find($draft->getId());
		$field = $updated?->getPayload()['fields']['income_cumulative'];

		self::assertSame('10500.0000', $field['value']);
		self::assertSame('manual', $field['source']);
		self::assertSame('10000.0000', $field['calculated']);
	}

	public function testPrintPageContainsWatermarkAndMarksExported(): void
	{
		$this->client->loginUser($this->createUser('tax-report-print-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$generator = static::getContainer()->get(TaxReportGeneratorService::class);
		$draft = $generator->generate($entity, 2026, 1);

		$this->client->request('GET', sprintf('/admin/tax/reports/%d/print', $draft->getId()));

		self::assertResponseIsSuccessful();
		// Test requests run in the EN locale; the UK locale renders «ЧЕРНЕТКА».
		self::assertSelectorTextContains('.watermark', 'DRAFT');
		self::assertSelectorTextContains('.disclaimer', 'verify before filing');

		$this->entityManager->clear();
		$updated = $this->entityManager->getRepository(TaxReportDraft::class)->find($draft->getId());
		self::assertSame('exported', $updated?->getStatus()->value);
	}

	public function testClosedPeriodBlocksManualIncomeAndReclassify(): void
	{
		$this->persistRateSet(2026);
		$uah = $this->persistCurrency('UAH');
		$entity = $this->persistLegalEntity(3, '5.00');
		$record = $this->persistIncomeRecord($entity, $uah, '500.0000', IncomeClassificationEnum::INCOME, '2026-02-10');

		$this->persistPeriod($entity, '2026-01-01', '2026-03-31', TaxPeriodStatusEnum::CLOSED);

		$manager = static::getContainer()->get(IncomeRecordManager::class);

		// Manual record in the closed period is rejected.
		$manual = (new IncomeRecord())
			->setLegalEntity($entity)
			->setRecognizedAt(new DateTimeImmutable('2026-02-20'))
			->setAmount('100.0000')
			->setCurrency($uah)
			->setAmountUah('100.0000')
			->setSourceType(IncomeSourceTypeEnum::MANUAL)
			->setClassification(IncomeClassificationEnum::INCOME);

		try {
			$manager->saveManual($manual);
			self::fail('Expected RuntimeException for a closed period.');
		} catch (RuntimeException $exception) {
			self::assertStringContainsString('Reopen the period', $exception->getMessage());
		}

		// Reclassification of an existing record in the closed period is rejected too.
		$this->expectException(RuntimeException::class);
		$manager->reclassify($record, IncomeClassificationEnum::NON_INCOME_OWN_FUNDS, 'test');
	}

	public function testReopenedPeriodAllowsChangesAndKeepsAuditLog(): void
	{
		$this->client->loginUser($this->createUser('tax-report-reopen-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$uah = $this->persistCurrency('UAH');
		$entity = $this->persistLegalEntity(3, '5.00');
		$period = $this->persistPeriod($entity, '2026-01-01', '2026-03-31', TaxPeriodStatusEnum::CLOSED);

		$crawler = $this->client->request('GET', sprintf('/admin/tax/reports/?entity=%d&year=2026', $entity->getId()));
		self::assertResponseIsSuccessful();

		$form = $crawler->filter(sprintf('form[action$="/admin/tax/reports/period/%d/reopen"]', $period->getId()))->form();
		$form['comment'] = 'Помилка в доході, виправляємо';

		$this->client->submit($form);

		$this->entityManager->clear();
		$updatedPeriod = $this->entityManager->getRepository(TaxReportingPeriod::class)->find($period->getId());

		self::assertSame(TaxPeriodStatusEnum::OPEN, $updatedPeriod?->getStatus());
		$log = $updatedPeriod?->getStatusLog();
		self::assertIsArray($log);
		self::assertSame('closed', $log[array_key_last($log)]['from']);
		self::assertSame('open', $log[array_key_last($log)]['to']);
		self::assertSame('Помилка в доході, виправляємо', $log[array_key_last($log)]['comment']);

		// Manual record in the reopened period now succeeds. Use services from the
		// current container — the kernel was rebooted by the HTTP requests above.
		$container = static::getContainer();
		$em = $container->get(EntityManagerInterface::class);
		$manager = $container->get(IncomeRecordManager::class);

		$manual = (new IncomeRecord())
			->setLegalEntity($em->getRepository(LegalEntity::class)->find($entity->getId()))
			->setRecognizedAt(new DateTimeImmutable('2026-02-20'))
			->setAmount('100.0000')
			->setCurrency($em->getRepository(Currency::class)->find('UAH'))
			->setAmountUah('100.0000')
			->setSourceType(IncomeSourceTypeEnum::MANUAL)
			->setClassification(IncomeClassificationEnum::INCOME);

		$manager->saveManual($manual);
		self::assertNotNull($manual->getId());
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

	private function persistLegalEntity(?int $epGroup, ?string $epRate): LegalEntity
	{
		$entity = (new LegalEntity())
			->setName('ФОП Декларант ' . uniqid())
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber(substr((string) random_int(1000000000, 9999999999), 0, 10))
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup($epGroup)
			->setEpRate($epRate);

		$this->entityManager->persist($entity);
		$this->entityManager->flush();

		return $entity;
	}

	private function persistPeriod(LegalEntity $entity, string $from, string $to, TaxPeriodStatusEnum $status): TaxReportingPeriod
	{
		$period = (new TaxReportingPeriod())
			->setLegalEntity($entity)
			->setType(TaxPeriodTypeEnum::QUARTER)
			->setDateFrom(new DateTimeImmutable($from))
			->setDateTo(new DateTimeImmutable($to))
			->setStatus($status);

		$this->entityManager->persist($period);
		$this->entityManager->flush();

		return $period;
	}

	private function persistRateSet(int $year): TaxRateSet
	{
		$existing = $this->entityManager->getRepository(TaxRateSet::class)->findOneBy(['year' => $year]);
		if ($existing instanceof TaxRateSet) {
			return $existing;
		}

		$rateSet = (new TaxRateSet())
			->setYear($year)
			->setMinimumWage('8647.0000')
			->setSubsistenceMinimum('3328.0000')
			->setGroup1IncomeLimit('1444049.0000')
			->setGroup2IncomeLimit('7211598.0000')
			->setGroup3IncomeLimit('10091049.0000')
			->setGroup1EpMonthly('332.8000')
			->setGroup2EpMonthly('1729.4000')
			->setGroup3EpRatePct('5.00')
			->setGroup3EpRateVatPct('3.00')
			->setEsvRatePct('22.00')
			->setEsvMonthlyMin('1902.3400')
			->setVzGroup12Monthly('864.7000')
			->setVzGroup3RatePct('1.00');

		$this->entityManager->persist($rateSet);
		$this->entityManager->flush();

		return $rateSet;
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

	private function persistIncomeRecord(
		LegalEntity $entity,
		Currency $currency,
		string $amountUah,
		IncomeClassificationEnum $classification,
		string $date,
	): IncomeRecord {
		$record = (new IncomeRecord())
			->setLegalEntity($entity)
			->setRecognizedAt(new DateTimeImmutable($date))
			->setAmount($amountUah)
			->setCurrency($currency)
			->setAmountUah($amountUah)
			->setNbuExchangeRate('1.00000000')
			->setSourceType(IncomeSourceTypeEnum::MANUAL)
			->setClassification($classification);

		$this->entityManager->persist($record);
		$this->entityManager->flush();

		return $record;
	}
}

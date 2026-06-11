<?php

namespace App\Tests\Controller;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
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

class TaxControlControllerTest extends WebTestCase
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
		$this->client->request('GET', '/admin/tax/control/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexShowsLegalEntitiesWithProgressBars(): void
	{
		$this->client->loginUser($this->createUser('tax-ctrl-' . uniqid() . '@example.com'));
		$rateSet = $this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');
		$uah = $this->persistCurrency('UAH');

		$limit = (float) $rateSet->getGroup3IncomeLimit();
		$income = round($limit * 0.75, 4);
		$this->persistIncomeRecord($entity, $uah, (string) $income, IncomeClassificationEnum::INCOME, '2026-04-01');

		$this->client->request('GET', '/admin/tax/control/?year=2026');

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', (string) $entity);
		self::assertSelectorExists('.progress-bar');
	}

	public function testIndexShowsWarningBadgeAbove70Percent(): void
	{
		$this->client->loginUser($this->createUser('tax-ctrl2-' . uniqid() . '@example.com'));
		$rateSet = $this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');
		$uah = $this->persistCurrency('UAH');

		$limit = (float) $rateSet->getGroup3IncomeLimit();
		$income = round($limit * 0.88, 4);
		$this->persistIncomeRecord($entity, $uah, (string) $income, IncomeClassificationEnum::INCOME, '2026-05-01');

		$this->client->request('GET', '/admin/tax/control/?year=2026');

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('.badge.bg-warning, .badge.bg-danger');
	}

	public function testShowPageDisplaysMonthlyAndQuarterlyBreakdown(): void
	{
		$this->client->loginUser($this->createUser('tax-ctrl3-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');
		$uah = $this->persistCurrency('UAH');

		$this->persistIncomeRecord($entity, $uah, '2000.0000', IncomeClassificationEnum::INCOME, '2026-02-15');
		$this->persistIncomeRecord($entity, $uah, '500.0000', IncomeClassificationEnum::REFUND, '2026-02-20');
		$this->persistIncomeRecord($entity, $uah, '3000.0000', IncomeClassificationEnum::INCOME, '2026-05-10');

		$this->client->request('GET', sprintf('/admin/tax/control/%d?year=2026', $entity->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', '2 000.00');
		self::assertSelectorTextContains('body', '3 000.00');
	}

	public function testShowPageLinksToIncomeRecords(): void
	{
		$this->client->loginUser($this->createUser('tax-ctrl4-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$this->client->request('GET', sprintf('/admin/tax/control/%d?year=2026', $entity->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorExists('a[href*="admin/tax/income"]');
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
			->setName('ФОП Ліміт ' . uniqid())
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber(substr((string) random_int(1000000000, 9999999999), 0, 10))
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup($epGroup)
			->setEpRate($epRate);

		$this->entityManager->persist($entity);
		$this->entityManager->flush();

		return $entity;
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

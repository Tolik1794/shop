<?php

namespace App\Tests\Service\Tax;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use App\Service\Tax\IncomeLimitService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IncomeLimitServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private IncomeLimitService $incomeLimitService;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->incomeLimitService = static::getContainer()->get(IncomeLimitService::class);
	}

	public function testNoRateSetReturnsNullLimit(): void
	{
		$entity = $this->persistLegalEntity(3, '5.00');

		$status = $this->incomeLimitService->buildStatus($entity, 1999);

		self::assertNull($status->limit);
		self::assertNull($status->percent);
		self::assertNull($status->remaining);
		self::assertSame('0.0000', $status->incomeTotal);
	}

	public function testNoEpGroupReturnsNullLimit(): void
	{
		$entity = $this->persistLegalEntity(null, null);
		$this->persistRateSet(2026);

		$status = $this->incomeLimitService->buildStatus($entity, 2026);

		self::assertNull($status->limit);
		self::assertNull($status->percent);
	}

	public function testGroup3PercentAndRemaining(): void
	{
		$uah = $this->persistCurrency('UAH');
		$rateSet = $this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$limit = (float) $rateSet->getGroup3IncomeLimit();
		$income = round($limit * 0.5, 4);
		$this->persistIncomeRecord($entity, $uah, (string) $income, IncomeClassificationEnum::INCOME, '2026-04-01');

		$status = $this->incomeLimitService->buildStatus($entity, 2026);

		self::assertSame($rateSet->getGroup3IncomeLimit(), $status->limit);
		self::assertNotNull($status->percent);
		self::assertEqualsWithDelta(50.0, $status->percent, 0.5);
		self::assertNotNull($status->remaining);
		self::assertEqualsWithDelta($limit * 0.5, (float) $status->remaining, 1.0);
		self::assertFalse($status->isThreshold70());
	}

	public function testRefundReducesNetIncome(): void
	{
		$uah = $this->persistCurrency('UAH');
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$this->persistIncomeRecord($entity, $uah, '1000.0000', IncomeClassificationEnum::INCOME, '2026-05-01');
		$this->persistIncomeRecord($entity, $uah, '300.0000', IncomeClassificationEnum::REFUND, '2026-05-15');

		$status = $this->incomeLimitService->buildStatus($entity, 2026);

		self::assertEqualsWithDelta(700.0, (float) $status->incomeTotal, 0.01);
	}

	public function testThresholdFlagsAt88Pct(): void
	{
		$uah = $this->persistCurrency('UAH');
		$rateSet = $this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$limit = (float) $rateSet->getGroup3IncomeLimit();
		$income = round($limit * 0.88, 4);
		$this->persistIncomeRecord($entity, $uah, (string) $income, IncomeClassificationEnum::INCOME, '2026-06-01');

		$status = $this->incomeLimitService->buildStatus($entity, 2026);

		self::assertTrue($status->isThreshold70());
		self::assertTrue($status->isThreshold85());
		self::assertFalse($status->isThreshold95());
		self::assertFalse($status->isExceeded());
		self::assertSame('warning', $status->getAlertLevel());
	}

	public function testThresholdFlagsAt96Pct(): void
	{
		$uah = $this->persistCurrency('UAH');
		$rateSet = $this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$limit = (float) $rateSet->getGroup3IncomeLimit();
		$income = round($limit * 0.96, 4);
		$this->persistIncomeRecord($entity, $uah, (string) $income, IncomeClassificationEnum::INCOME, '2026-07-01');

		$status = $this->incomeLimitService->buildStatus($entity, 2026);

		self::assertTrue($status->isThreshold70());
		self::assertTrue($status->isThreshold85());
		self::assertTrue($status->isThreshold95());
		self::assertFalse($status->isExceeded());
		self::assertSame('critical', $status->getAlertLevel());
	}

	public function testExceededStatus(): void
	{
		$uah = $this->persistCurrency('UAH');
		$rateSet = $this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$limit = (float) $rateSet->getGroup3IncomeLimit();
		$this->persistIncomeRecord($entity, $uah, (string) ($limit + 1000), IncomeClassificationEnum::INCOME, '2026-07-01');

		$status = $this->incomeLimitService->buildStatus($entity, 2026);

		self::assertTrue($status->isExceeded());
		self::assertSame('exceeded', $status->getAlertLevel());
	}

	public function testMonthlyBreakdownSumsCorrectly(): void
	{
		$uah = $this->persistCurrency('UAH');
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$this->persistIncomeRecord($entity, $uah, '1000.0000', IncomeClassificationEnum::INCOME, '2026-01-15');
		$this->persistIncomeRecord($entity, $uah, '500.0000', IncomeClassificationEnum::INCOME, '2026-03-10');
		$this->persistIncomeRecord($entity, $uah, '200.0000', IncomeClassificationEnum::REFUND, '2026-03-20');

		$repo = static::getContainer()->get(\App\Repository\IncomeRecordRepository::class);
		$byMonth = $repo->getMonthlyBreakdown($entity, 2026);

		self::assertArrayHasKey(1, $byMonth);
		self::assertEqualsWithDelta(1000.0, (float) $byMonth[1]['income'], 0.01);
		self::assertEqualsWithDelta(0.0, (float) $byMonth[1]['refund'], 0.01);
		self::assertArrayHasKey(3, $byMonth);
		self::assertEqualsWithDelta(500.0, (float) $byMonth[3]['income'], 0.01);
		self::assertEqualsWithDelta(200.0, (float) $byMonth[3]['refund'], 0.01);
		self::assertEqualsWithDelta(300.0, (float) $byMonth[3]['net'], 0.01);
	}

	private function persistLegalEntity(?int $epGroup, ?string $epRate): LegalEntity
	{
		$entity = (new LegalEntity())
			->setName('ФОП Тест ' . uniqid())
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

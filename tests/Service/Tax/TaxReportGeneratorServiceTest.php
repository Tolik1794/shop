<?php

namespace App\Tests\Service\Tax;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
use App\Entity\TaxReportDraft;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use App\Service\Tax\TaxReportGeneratorService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaxReportGeneratorServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private TaxReportGeneratorService $generator;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->generator = static::getContainer()->get(TaxReportGeneratorService::class);
	}

	public function testGroup3QuarterlyCumulativeMatchesManualCalculation(): void
	{
		$uah = $this->persistCurrency('UAH');
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		// Q1: 10 000 income; Q2: 20 000 income, 1 000 refund.
		$this->persistIncomeRecord($entity, $uah, '10000.0000', IncomeClassificationEnum::INCOME, '2026-02-10');
		$this->persistIncomeRecord($entity, $uah, '20000.0000', IncomeClassificationEnum::INCOME, '2026-05-12');
		$this->persistIncomeRecord($entity, $uah, '1000.0000', IncomeClassificationEnum::REFUND, '2026-06-01');

		$draft = $this->generator->generate($entity, 2026, 2);

		$fields = $draft->getPayload()['fields'];

		// Cumulative Jan-Jun: 10000 + 20000 - 1000 = 29000
		self::assertSame('29000.0000', $fields['income_cumulative']['value']);
		// Previous (Q1): 10000
		self::assertSame('10000.0000', $fields['income_previous']['value']);
		self::assertSame('19000.0000', $fields['income_quarter']['value']);
		// EP 5%: cumulative 1450, previous 500, due 950
		self::assertSame('1450.0000', $fields['ep_cumulative']['value']);
		self::assertSame('500.0000', $fields['ep_previous']['value']);
		self::assertSame('950.0000', $fields['ep_due']['value']);
		// VZ 1%: cumulative 290, previous 100, due 190
		self::assertSame('290.0000', $fields['vz_cumulative']['value']);
		self::assertSame('190.0000', $fields['vz_due']['value']);
		// ESV appendix: 1902.34 × 3 = 5707.02
		self::assertSame('5707.0200', $fields['esv_total']['value']);
		self::assertStringContainsString('57', $draft->getFormVersion());
	}

	public function testGroup1AnnualDeclaration(): void
	{
		$uah = $this->persistCurrency('UAH');
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(1, null);

		$this->persistIncomeRecord($entity, $uah, '50000.0000', IncomeClassificationEnum::INCOME, '2026-03-01');

		$draft = $this->generator->generate($entity, 2026, null);

		$fields = $draft->getPayload()['fields'];

		self::assertSame('50000.0000', $fields['income_year']['value']);
		// EP group 1: 332.80 × 12 = 3993.60
		self::assertSame('332.8000', $fields['ep_monthly']['value']);
		self::assertSame('3993.6000', $fields['ep_total']['value']);
		// VZ groups 1-2: 864.70 × 12 = 10376.40
		self::assertSame('10376.4000', $fields['vz_total']['value']);
		// ESV: 1902.34 × 12 = 22828.08
		self::assertSame('22828.0800', $fields['esv_total']['value']);
	}

	public function testEsvExemptEntityGetsZeroEsv(): void
	{
		$this->persistCurrency('UAH');
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00')->setEsvExempt(true);
		$this->entityManager->flush();

		$draft = $this->generator->generate($entity, 2026, 1);

		self::assertSame('0.0000', $draft->getPayload()['fields']['esv_total']['value']);
	}

	public function testRegenerationUpdatesExistingDraftWithoutDuplicates(): void
	{
		$uah = $this->persistCurrency('UAH');
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$first = $this->generator->generate($entity, 2026, 1);

		$this->persistIncomeRecord($entity, $uah, '5000.0000', IncomeClassificationEnum::INCOME, '2026-01-20');

		$second = $this->generator->generate($entity, 2026, 1);

		self::assertSame($first->getId(), $second->getId());
		self::assertSame('5000.0000', $second->getPayload()['fields']['income_cumulative']['value']);
		self::assertCount(1, $this->entityManager->getRepository(TaxReportDraft::class)->findBy([
			'legalEntity' => $entity,
		]));
	}

	public function testMissingRateSetThrows(): void
	{
		$entity = $this->persistLegalEntity(3, '5.00');

		$this->expectException(RuntimeException::class);

		$this->generator->generate($entity, 1999, 1);
	}

	public function testGroup3WithoutQuarterThrows(): void
	{
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$this->expectException(RuntimeException::class);

		$this->generator->generate($entity, 2026, null);
	}

	private function persistLegalEntity(?int $epGroup, ?string $epRate): LegalEntity
	{
		$entity = (new LegalEntity())
			->setName('ФОП Звітний ' . uniqid())
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

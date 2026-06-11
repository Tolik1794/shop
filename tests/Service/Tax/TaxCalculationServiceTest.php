<?php

namespace App\Tests\Service\Tax;

use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use App\Service\Tax\TaxCalculationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TaxCalculationServiceTest extends TestCase
{
	private TaxCalculationService $calc;
	private TaxRateSet $rateSet;

	protected function setUp(): void
	{
		$this->calc = new TaxCalculationService();

		$this->rateSet = (new TaxRateSet())
			->setYear(2026)
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
	}

	// ─── EP Group 3 ─────────────────────────────────────────────────────────────

	public function testEpGroup3Standard5Pct(): void
	{
		$result = $this->calc->calculateEpGroup3('100000.0000', '5.00');

		self::assertSame('5000.0000', $result);
	}

	public function testEpGroup3Vat3Pct(): void
	{
		$result = $this->calc->calculateEpGroup3('100000.0000', '3.00');

		self::assertSame('3000.0000', $result);
	}

	public function testEpGroup3RoundsToKopek(): void
	{
		// 100001.333 × 0.05 = 5000.06665 → round to kopek → 5000.07 → stored as 5000.0700
		$result = $this->calc->calculateEpGroup3('100001.3330', '5.00');

		self::assertSame('5000.0700', $result);
	}

	public function testEpGroup3ZeroIncome(): void
	{
		$result = $this->calc->calculateEpGroup3('0.0000', '5.00');

		self::assertSame('0.0000', $result);
	}

	// ─── EP Monthly (groups 1–2) ─────────────────────────────────────────────────

	public function testEpGroup1Monthly(): void
	{
		$result = $this->calc->calculateEpMonthly(1, $this->rateSet);

		self::assertSame('332.8000', $result);
	}

	public function testEpGroup2Monthly(): void
	{
		$result = $this->calc->calculateEpMonthly(2, $this->rateSet);

		self::assertSame('1729.4000', $result);
	}

	// ─── ESV ─────────────────────────────────────────────────────────────────────

	public function testEsvQuarterlyNotExempt(): void
	{
		// 1902.34 × 3 = 5707.02
		$result = $this->calc->calculateEsvQuarterly($this->rateSet, 3, false);

		self::assertSame('5707.0200', $result);
	}

	public function testEsvQuarterlyExemptReturnsZero(): void
	{
		$result = $this->calc->calculateEsvQuarterly($this->rateSet, 3, true);

		self::assertSame('0.0000', $result);
	}

	public function testEsvQuarterlyPartialQuarter2Months(): void
	{
		// 1902.34 × 2 = 3804.68
		$result = $this->calc->calculateEsvQuarterly($this->rateSet, 2, false);

		self::assertSame('3804.6800', $result);
	}

	// ─── VZ ──────────────────────────────────────────────────────────────────────

	public function testVzGroup3(): void
	{
		// 100000 × 1% = 1000.00
		$result = $this->calc->calculateVzGroup3('100000.0000', $this->rateSet);

		self::assertSame('1000.0000', $result);
	}

	public function testVzGroup12Monthly(): void
	{
		$result = $this->calc->calculateVzMonthly($this->rateSet);

		self::assertSame('864.7000', $result);
	}

	public function testVzGroup3RoundsToKopek(): void
	{
		// 99999.999 × 0.01 = 999.99999 → rounds to 1000.00
		$result = $this->calc->calculateVzGroup3('99999.9990', $this->rateSet);

		self::assertSame('1000.0000', $result);
	}

	// ─── Due dates ───────────────────────────────────────────────────────────────

	public function testEpDueDateGroup3Q1(): void
	{
		$due = $this->calc->epDueDateGroup3(2026, 1);

		self::assertSame('2026-04-10', $due->format('Y-m-d'));
	}

	public function testEpDueDateGroup3Q4WrapsToNextYear(): void
	{
		$due = $this->calc->epDueDateGroup3(2026, 4);

		self::assertSame('2027-01-10', $due->format('Y-m-d'));
	}

	public function testEsvDueDateQ2(): void
	{
		$due = $this->calc->esvDueDate(2026, 2);

		self::assertSame('2026-07-20', $due->format('Y-m-d'));
	}

	public function testEsvDueDateQ4WrapsToNextYear(): void
	{
		$due = $this->calc->esvDueDate(2026, 4);

		self::assertSame('2027-01-20', $due->format('Y-m-d'));
	}

	public function testEpVzDueDateMonthlyIsThe20th(): void
	{
		$due = $this->calc->epVzDueDateMonthly(2026, 6);

		self::assertSame('2026-06-20', $due->format('Y-m-d'));
	}

	// ─── Quarter date range ───────────────────────────────────────────────────────

	public function testQuarterDateRangeQ1(): void
	{
		['from' => $from, 'to' => $to] = $this->calc->quarterDateRange(2026, 1);

		self::assertSame('2026-01-01', $from->format('Y-m-d'));
		self::assertSame('2026-03-31', $to->format('Y-m-d'));
	}

	public function testQuarterDateRangeQ4(): void
	{
		['from' => $from, 'to' => $to] = $this->calc->quarterDateRange(2026, 4);

		self::assertSame('2026-10-01', $from->format('Y-m-d'));
		self::assertSame('2026-12-31', $to->format('Y-m-d'));
	}

	public function testMonthDateRangeFebruary(): void
	{
		['from' => $from, 'to' => $to] = $this->calc->monthDateRange(2026, 2);

		self::assertSame('2026-02-01', $from->format('Y-m-d'));
		self::assertSame('2026-02-28', $to->format('Y-m-d'));
	}

	// ─── Group 3 EP rate selection ────────────────────────────────────────────────

	public function testGroup3EpRateUsesVatRateWhenVatPayer(): void
	{
		$entity = $this->makeEntity(3, '5.00', vatPayer: true);

		$rate = $this->calc->group3EpRate($entity, $this->rateSet);

		self::assertSame('3.00', $rate);
	}

	public function testGroup3EpRateUsesEntityRateWhenNotVatPayer(): void
	{
		$entity = $this->makeEntity(3, '5.00', vatPayer: false);

		$rate = $this->calc->group3EpRate($entity, $this->rateSet);

		self::assertSame('5.00', $rate);
	}

	public function testGroup3EpRateFallsBackToRateSetWhenEntityRateNull(): void
	{
		$entity = $this->makeEntity(3, null, vatPayer: false);

		$rate = $this->calc->group3EpRate($entity, $this->rateSet);

		self::assertSame('5.00', $rate);
	}

	private function makeEntity(int $epGroup, ?string $epRate, bool $vatPayer): LegalEntity
	{
		return (new LegalEntity())
			->setName('Test')
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber('1234567890')
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup($epGroup)
			->setEpRate($epRate)
			->setVatPayer($vatPayer);
	}
}

<?php

namespace App\Service\Tax;

use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
use DateTimeImmutable;

/**
 * Pure tax formula calculations — no DB access, no side effects.
 *
 * Rounding convention: amounts are rounded to 2 decimal places (kopeks),
 * then stored as decimal(14,4) strings.
 */
final class TaxCalculationService
{
	// ─── EP formulas ────────────────────────────────────────────────────────────

	/**
	 * Group 3 quarterly EP: epRate% × net quarterly income.
	 */
	public function calculateEpGroup3(string $netIncome, string $ratePct): string
	{
		$amount = (float) $netIncome * ((float) $ratePct / 100);

		return $this->round($amount);
	}

	/**
	 * Group 1 or 2 monthly EP: fixed rate from TaxRateSet.
	 */
	public function calculateEpMonthly(int $epGroup, TaxRateSet $rateSet): string
	{
		$amount = match($epGroup) {
			1 => (float) $rateSet->getGroup1EpMonthly(),
			2 => (float) $rateSet->getGroup2EpMonthly(),
			default => 0.0,
		};

		return $this->round($amount);
	}

	// ─── ESV formulas ────────────────────────────────────────────────────────────

	/**
	 * ESV for N months (quarterly: 3 months). Returns 0 if exempt.
	 */
	public function calculateEsvQuarterly(TaxRateSet $rateSet, int $months, bool $exempt): string
	{
		if ($exempt) {
			return '0.0000';
		}

		$amount = (float) $rateSet->getEsvMonthlyMin() * $months;

		return $this->round($amount);
	}

	// ─── VZ formulas ────────────────────────────────────────────────────────────

	/**
	 * Group 3 quarterly VZ: vzGroup3RatePct% × net quarterly income.
	 */
	public function calculateVzGroup3(string $netIncome, TaxRateSet $rateSet): string
	{
		$amount = (float) $netIncome * ((float) $rateSet->getVzGroup3RatePct() / 100);

		return $this->round($amount);
	}

	/**
	 * Group 1 or 2 monthly VZ: fixed rate from TaxRateSet.
	 */
	public function calculateVzMonthly(TaxRateSet $rateSet): string
	{
		return $this->round((float) $rateSet->getVzGroup12Monthly());
	}

	// ─── Due date helpers ────────────────────────────────────────────────────────

	/**
	 * EP/VZ due date for Group 3: 10th of the first month of the next quarter.
	 * Q4 → January 10th of the next year.
	 */
	public function epDueDateGroup3(int $year, int $quarter): DateTimeImmutable
	{
		[$nextYear, $firstMonth] = $this->nextQuarterFirstMonth($year, $quarter);

		return new DateTimeImmutable(sprintf('%04d-%02d-10', $nextYear, $firstMonth));
	}

	/**
	 * ESV due date (all groups): 20th of the first month of the next quarter.
	 * Q4 → January 20th of the next year.
	 */
	public function esvDueDate(int $year, int $quarter): DateTimeImmutable
	{
		[$nextYear, $firstMonth] = $this->nextQuarterFirstMonth($year, $quarter);

		return new DateTimeImmutable(sprintf('%04d-%02d-20', $nextYear, $firstMonth));
	}

	/**
	 * EP/VZ due date for Groups 1–2 monthly advance: 20th of the same month.
	 */
	public function epVzDueDateMonthly(int $year, int $month): DateTimeImmutable
	{
		return new DateTimeImmutable(sprintf('%04d-%02d-20', $year, $month));
	}

	// ─── Period helpers ──────────────────────────────────────────────────────────

	public function quarterDateRange(int $year, int $quarter): array
	{
		$firstMonth = ($quarter - 1) * 3 + 1;
		$lastMonth = $firstMonth + 2;
		$lastDay = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $lastMonth)))->format('t');

		return [
			'from' => new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $firstMonth)),
			'to'   => new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $lastMonth, $lastDay)),
		];
	}

	public function monthDateRange(int $year, int $month): array
	{
		$lastDay = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');

		return [
			'from' => new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)),
			'to'   => new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $lastDay)),
		];
	}

	/**
	 * EP rate for Group 3: use VAT rate if entity is a VAT payer, otherwise standard rate.
	 */
	public function group3EpRate(LegalEntity $entity, TaxRateSet $rateSet): string
	{
		if ($entity->isVatPayer()) {
			return $rateSet->getGroup3EpRateVatPct();
		}

		return $entity->getEpRate() ?? $rateSet->getGroup3EpRatePct();
	}

	// ─── Private ─────────────────────────────────────────────────────────────────

	private function round(float $amount): string
	{
		return number_format(round($amount, 2), 4, '.', '');
	}

	/** @return array{int, int} [year, month] of first month of next quarter */
	private function nextQuarterFirstMonth(int $year, int $quarter): array
	{
		if ($quarter === 4) {
			return [$year + 1, 1];
		}

		return [$year, $quarter * 3 + 1];
	}
}

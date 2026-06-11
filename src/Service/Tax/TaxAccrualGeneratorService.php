<?php

namespace App\Service\Tax;

use App\Entity\LegalEntity;
use App\Entity\TaxAccrual;
use App\Entity\TaxRateSet;
use App\Enum\TaxAccrualStatusEnum;
use App\Enum\TaxPeriodTypeEnum;
use App\Enum\TaxTypeEnum;
use App\Repository\IncomeRecordRepository;
use App\Repository\LegalEntityRepository;
use App\Repository\TaxAccrualRepository;
use App\Repository\TaxRateSetRepository;
use App\Repository\TaxReportingPeriodRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TaxAccrualGeneratorService
{
	public function __construct(
		private readonly TaxCalculationService $calc,
		private readonly IncomeRecordRepository $incomeRecordRepository,
		private readonly TaxRateSetRepository $taxRateSetRepository,
		private readonly TaxReportingPeriodRepository $periodRepository,
		private readonly TaxAccrualRepository $accrualRepository,
		private readonly LegalEntityRepository $legalEntityRepository,
		private readonly EntityManagerInterface $em,
	) {}

	/**
	 * Generate (or update) accruals for a single legal entity and year.
	 *
	 * @return array{created: int, updated: int, skipped: int, errors: string[]}
	 */
	public function generate(LegalEntity $entity, int $year, bool $apply): array
	{
		$report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

		$rateSet = $this->taxRateSetRepository->findByYear($year);
		if ($rateSet === null) {
			$report['errors'][] = sprintf('No TaxRateSet for year %d — cannot generate accruals.', $year);

			return $report;
		}

		$epGroup = $entity->getEpGroup();
		if ($epGroup === null) {
			$report['errors'][] = 'Legal entity has no EP group — accruals not generated.';

			return $report;
		}

		if ($apply) {
			$this->em->wrapInTransaction(function () use ($entity, $year, $rateSet, $epGroup, &$report) {
				$this->doGenerate($entity, $year, $rateSet, $epGroup, $report);
			});
		} else {
			$this->doGenerate($entity, $year, $rateSet, $epGroup, $report, dryRun: true);
		}

		return $report;
	}

	/**
	 * Generate accruals for all active legal entities for the given year.
	 *
	 * @return array<string, array{created: int, updated: int, skipped: int, errors: string[]}>
	 */
	public function generateAll(int $year, bool $apply): array
	{
		$results = [];
		foreach ($this->legalEntityRepository->findActive() as $entity) {
			$results[(string) $entity] = $this->generate($entity, $year, $apply);
		}

		return $results;
	}

	private function doGenerate(
		LegalEntity $entity,
		int $year,
		TaxRateSet $rateSet,
		int $epGroup,
		array &$report,
		bool $dryRun = false,
	): void {
		if ($epGroup === 3) {
			$this->generateGroup3($entity, $year, $rateSet, $report, $dryRun);
		} else {
			$this->generateGroup12($entity, $year, $rateSet, $epGroup, $report, $dryRun);
		}
	}

	private function generateGroup3(
		LegalEntity $entity,
		int $year,
		TaxRateSet $rateSet,
		array &$report,
		bool $dryRun,
	): void {
		$epRate = $this->calc->group3EpRate($entity, $rateSet);

		for ($q = 1; $q <= 4; $q++) {
			['from' => $from, 'to' => $to] = $this->calc->quarterDateRange($year, $q);

			$income = $this->incomeRecordRepository->getNetIncomeForPeriod($entity, $from, $to);

			$epAmount = $this->calc->calculateEpGroup3($income, $epRate);
			$epDue = $this->calc->epDueDateGroup3($year, $q);

			$vzAmount = $this->calc->calculateVzGroup3($income, $rateSet);
			$vzDue = $this->calc->epDueDateGroup3($year, $q);

			$esvAmount = $this->calc->calculateEsvQuarterly($rateSet, 3, $entity->isEsvExempt());
			$esvDue = $this->calc->esvDueDate($year, $q);

			if ($dryRun) {
				$report['created'] += 3;
				continue;
			}

			$period = $this->periodRepository->findOrCreate($entity, TaxPeriodTypeEnum::QUARTER, $from, $to);
			$this->em->flush();

			$this->upsertAccrual($entity, $period, TaxTypeEnum::EP, $epAmount, $epDue, $report);
			$this->upsertAccrual($entity, $period, TaxTypeEnum::VZ, $vzAmount, $vzDue, $report);
			$this->upsertAccrual($entity, $period, TaxTypeEnum::ESV, $esvAmount, $esvDue, $report);
		}
	}

	private function generateGroup12(
		LegalEntity $entity,
		int $year,
		TaxRateSet $rateSet,
		int $epGroup,
		array &$report,
		bool $dryRun,
	): void {
		$epAmount = $this->calc->calculateEpMonthly($epGroup, $rateSet);
		$vzAmount = $this->calc->calculateVzMonthly($rateSet);

		for ($m = 1; $m <= 12; $m++) {
			['from' => $from, 'to' => $to] = $this->calc->monthDateRange($year, $m);
			$dueDate = $this->calc->epVzDueDateMonthly($year, $m);

			if ($dryRun) {
				$report['created'] += 2;
				continue;
			}

			$period = $this->periodRepository->findOrCreate($entity, TaxPeriodTypeEnum::MONTH, $from, $to);
			$this->em->flush();

			$this->upsertAccrual($entity, $period, TaxTypeEnum::EP, $epAmount, $dueDate, $report);
			$this->upsertAccrual($entity, $period, TaxTypeEnum::VZ, $vzAmount, $dueDate, $report);
		}

		for ($q = 1; $q <= 4; $q++) {
			['from' => $from, 'to' => $to] = $this->calc->quarterDateRange($year, $q);
			$esvAmount = $this->calc->calculateEsvQuarterly($rateSet, 3, $entity->isEsvExempt());
			$esvDue = $this->calc->esvDueDate($year, $q);

			if ($dryRun) {
				$report['created'] += 1;
				continue;
			}

			$period = $this->periodRepository->findOrCreate($entity, TaxPeriodTypeEnum::QUARTER, $from, $to);
			$this->em->flush();

			$this->upsertAccrual($entity, $period, TaxTypeEnum::ESV, $esvAmount, $esvDue, $report);
		}
	}

	private function upsertAccrual(
		LegalEntity $entity,
		\App\Entity\TaxReportingPeriod $period,
		TaxTypeEnum $taxType,
		string $amount,
		\DateTimeImmutable $dueDate,
		array &$report,
	): void {
		$existing = $this->accrualRepository->findOneBy([
			'legalEntity' => $entity,
			'period'      => $period,
			'taxType'     => $taxType,
		]);

		if ($existing instanceof TaxAccrual) {
			if ($existing->getStatus() === TaxAccrualStatusEnum::PAID) {
				$report['skipped']++;

				return;
			}
			$existing->setAccruedAmount($amount);
			$report['updated']++;

			return;
		}

		$accrual = (new TaxAccrual())
			->setLegalEntity($entity)
			->setPeriod($period)
			->setTaxType($taxType)
			->setAccruedAmount($amount)
			->setDueDate($dueDate);

		$this->em->persist($accrual);
		$report['created']++;
	}
}

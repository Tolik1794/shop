<?php

namespace App\Service\Tax;

use App\Entity\LegalEntity;
use App\Entity\TaxRateSet;
use App\Entity\TaxReportDraft;
use App\Entity\User\User;
use App\Enum\TaxPeriodTypeEnum;
use App\Enum\TaxReportDraftStatusEnum;
use App\Repository\IncomeRecordRepository;
use App\Repository\TaxRateSetRepository;
use App\Repository\TaxReportDraftRepository;
use App\Repository\TaxReportingPeriodRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Builds EP declaration drafts for FOP on the simplified system.
 *
 * Group 3: quarterly declaration with year-to-date (cumulative) totals.
 * Groups 1-2: annual declaration with fixed monthly EP/VZ and ESV appendix.
 *
 * Every figure is reference-only and must be verified before filing.
 *
 * TODO tax-module: XML export for the Electronic Cabinet is intentionally not
 * implemented — the official form code and XSD for the current form version
 * could not be reliably verified. Generate the print form and transfer the
 * values manually until the XSD is confirmed.
 */
final class TaxReportGeneratorService
{
	// Verify the current form before filing — fixed at generation time in the draft.
	public const string REPORT_TYPE_EP_DECLARATION = 'ep_declaration';
	public const string FORM_VERSION = 'Наказ Мінфіну від 31.01.2025 № 57 (перевірити актуальність форми перед поданням)';

	public function __construct(
		private readonly TaxCalculationService $calc,
		private readonly IncomeRecordRepository $incomeRecordRepository,
		private readonly TaxRateSetRepository $taxRateSetRepository,
		private readonly TaxReportingPeriodRepository $periodRepository,
		private readonly TaxReportDraftRepository $draftRepository,
		private readonly EntityManagerInterface $em,
	)
	{
	}

	/**
	 * Group 3 requires a quarter (cumulative declaration); groups 1-2 use
	 * quarter = null for the annual declaration.
	 */
	public function generate(LegalEntity $entity, int $year, ?int $quarter, ?User $actor = null): TaxReportDraft
	{
		$epGroup = $entity->getEpGroup();
		if ($epGroup === null) {
			throw new RuntimeException('Legal entity has no single tax group — declaration cannot be generated.');
		}

		$rateSet = $this->taxRateSetRepository->findByYear($year);
		if ($rateSet === null) {
			throw new RuntimeException(sprintf('No tax rate set for year %d — add it before generating reports.', $year));
		}

		if ($epGroup === 3) {
			if ($quarter === null || $quarter < 1 || $quarter > 4) {
				throw new RuntimeException('Group 3 declaration requires a quarter (1-4).');
			}

			return $this->generateGroup3($entity, $year, $quarter, $rateSet, $actor);
		}

		return $this->generateGroup12($entity, $year, $epGroup, $rateSet, $actor);
	}

	private function generateGroup3(LegalEntity $entity, int $year, int $quarter, TaxRateSet $rateSet, ?User $actor): TaxReportDraft
	{
		['from' => $quarterFrom, 'to' => $quarterTo] = $this->calc->quarterDateRange($year, $quarter);
		$yearStart = new DateTimeImmutable("{$year}-01-01");

		$incomeCumulative = $this->incomeRecordRepository->getNetIncomeForPeriod($entity, $yearStart, $quarterTo);
		$incomePrevious = $quarter > 1
			? $this->incomeRecordRepository->getNetIncomeForPeriod(
				$entity,
				$yearStart,
				$this->calc->quarterDateRange($year, $quarter - 1)['to'],
			)
			: '0.0000';

		$epRate = $this->calc->group3EpRate($entity, $rateSet);
		$epCumulative = $this->calc->calculateEpGroup3($incomeCumulative, $epRate);
		$epPrevious = $this->calc->calculateEpGroup3($incomePrevious, $epRate);

		$vzCumulative = $this->calc->calculateVzGroup3($incomeCumulative, $rateSet);
		$vzPrevious = $this->calc->calculateVzGroup3($incomePrevious, $rateSet);

		$esvTotal = $this->calc->calculateEsvQuarterly($rateSet, 3, $entity->isEsvExempt());

		$fields = [
			'income_cumulative' => $incomeCumulative,
			'income_previous'   => $incomePrevious,
			'income_quarter'    => $this->diff($incomeCumulative, $incomePrevious),
			'ep_rate_pct'       => $epRate,
			'ep_cumulative'     => $epCumulative,
			'ep_previous'       => $epPrevious,
			'ep_due'            => $this->diff($epCumulative, $epPrevious),
			'vz_rate_pct'       => $rateSet->getVzGroup3RatePct(),
			'vz_cumulative'     => $vzCumulative,
			'vz_previous'       => $vzPrevious,
			'vz_due'            => $this->diff($vzCumulative, $vzPrevious),
			'esv_monthly_min'   => $rateSet->getEsvMonthlyMin(),
			'esv_months'        => '3',
			'esv_total'         => $esvTotal,
		];

		$period = $this->periodRepository->findOrCreate($entity, TaxPeriodTypeEnum::QUARTER, $quarterFrom, $quarterTo);
		$this->em->flush();

		return $this->upsertDraft($entity, $period, $fields, [
			'year'         => $year,
			'quarter'      => $quarter,
			'ep_group'     => 3,
			'period_label' => sprintf('%d квартал %d (наростаючим підсумком)', $quarter, $year),
			'esv_exempt'   => $entity->isEsvExempt(),
		], $actor);
	}

	private function generateGroup12(LegalEntity $entity, int $year, int $epGroup, TaxRateSet $rateSet, ?User $actor): TaxReportDraft
	{
		$yearStart = new DateTimeImmutable("{$year}-01-01");
		$yearEnd = new DateTimeImmutable("{$year}-12-31");

		$incomeYear = $this->incomeRecordRepository->getNetIncomeForPeriod($entity, $yearStart, $yearEnd);

		$epMonthly = $this->calc->calculateEpMonthly($epGroup, $rateSet);
		$vzMonthly = $this->calc->calculateVzMonthly($rateSet);
		$esvTotal = $this->calc->calculateEsvQuarterly($rateSet, 12, $entity->isEsvExempt());

		$fields = [
			'income_year'     => $incomeYear,
			'ep_monthly'      => $epMonthly,
			'ep_total'        => $this->multiply($epMonthly, 12),
			'vz_monthly'      => $vzMonthly,
			'vz_total'        => $this->multiply($vzMonthly, 12),
			'esv_monthly_min' => $rateSet->getEsvMonthlyMin(),
			'esv_months'      => '12',
			'esv_total'       => $esvTotal,
		];

		$period = $this->periodRepository->findOrCreate($entity, TaxPeriodTypeEnum::YEAR, $yearStart, $yearEnd);
		$this->em->flush();

		return $this->upsertDraft($entity, $period, $fields, [
			'year'         => $year,
			'quarter'      => null,
			'ep_group'     => $epGroup,
			'period_label' => sprintf('%d рік (річна декларація, група %d)', $year, $epGroup),
			'esv_exempt'   => $entity->isEsvExempt(),
		], $actor);
	}

	/**
	 * @param array<string, string> $fields
	 * @param array<string, mixed> $meta
	 */
	private function upsertDraft(
		LegalEntity $entity,
		\App\Entity\TaxReportingPeriod $period,
		array $fields,
		array $meta,
		?User $actor,
	): TaxReportDraft {
		$payloadFields = [];
		foreach ($fields as $key => $value) {
			$payloadFields[$key] = [
				'value'      => $value,
				'calculated' => $value,
				'source'     => 'calculated',
			];
		}

		$payload = [
			'meta'   => $meta + [
				'entity_name'  => $entity->getName(),
				'tax_number'   => $entity->getTaxNumber(),
				'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
			],
			'fields' => $payloadFields,
		];

		$draft = $this->draftRepository->findOneBy(
			[
				'legalEntity' => $entity,
				'period'      => $period,
				'reportType'  => self::REPORT_TYPE_EP_DECLARATION,
			],
			['id' => 'DESC'],
		);

		// Regeneration rebuilds the payload from scratch — manual corrections
		// are intentionally dropped because the underlying data has changed.
		if (!$draft instanceof TaxReportDraft) {
			$draft = (new TaxReportDraft())
				->setLegalEntity($entity)
				->setPeriod($period)
				->setReportType(self::REPORT_TYPE_EP_DECLARATION)
				->setCreatedBy($actor);
			$this->em->persist($draft);
		}

		$draft
			->setFormVersion(self::FORM_VERSION)
			->setPayload($payload)
			->setGeneratedAt(new DateTimeImmutable())
			->setStatus(TaxReportDraftStatusEnum::DRAFT);

		$this->em->flush();

		return $draft;
	}

	private function diff(string $a, string $b): string
	{
		return number_format((float) $a - (float) $b, 4, '.', '');
	}

	private function multiply(string $a, int $times): string
	{
		return number_format(round((float) $a * $times, 2), 4, '.', '');
	}
}

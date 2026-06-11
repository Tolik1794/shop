<?php

namespace App\Service\Tax;

use App\Dto\Tax\LimitStatusDto;
use App\Entity\LegalEntity;
use App\Repository\IncomeRecordRepository;
use App\Repository\LegalEntityRepository;
use App\Repository\TaxRateSetRepository;
use DateTimeImmutable;

final class IncomeLimitService
{
	public function __construct(
		private readonly IncomeRecordRepository $incomeRecordRepository,
		private readonly TaxRateSetRepository $taxRateSetRepository,
		private readonly LegalEntityRepository $legalEntityRepository,
	) {}

	public function buildStatus(LegalEntity $entity, int $year): LimitStatusDto
	{
		$incomeTotal = $this->incomeRecordRepository->getNetIncomeForYear($entity, $year);
		$limit = $this->resolveLimit($entity, $year);

		$percent = null;
		$remaining = null;
		if ($limit !== null && (float) $limit > 0) {
			$percent = round(((float) $incomeTotal / (float) $limit) * 100, 2);
			$remaining = number_format((float) $limit - (float) $incomeTotal, 4, '.', '');
		}

		$avgDaily90 = $this->incomeRecordRepository->getAvgDailyIncome($entity, 90);

		$forecastAnnual = null;
		$forecastLimitDate = null;

		if ((float) $avgDaily90 > 0) {
			$today = new DateTimeImmutable();
			$yearEnd = new DateTimeImmutable("{$year}-12-31");
			$daysLeft = max(0, (int) $today->diff($yearEnd)->days);

			$forecastAnnual = number_format(
				(float) $incomeTotal + ((float) $avgDaily90 * $daysLeft),
				4,
				'.',
				'',
			);

			if ($remaining !== null && (float) $remaining > 0) {
				$daysToLimit = (int) ceil((float) $remaining / (float) $avgDaily90);
				$forecastLimitDate = $today->modify("+{$daysToLimit} days");
			}
		}

		return new LimitStatusDto(
			legalEntity: $entity,
			year: $year,
			incomeTotal: $incomeTotal,
			limit: $limit,
			percent: $percent,
			remaining: $remaining,
			avgDaily90: $avgDaily90,
			forecastAnnual: $forecastAnnual,
			forecastLimitDate: $forecastLimitDate,
		);
	}

	/**
	 * @return LimitStatusDto[]
	 */
	public function buildAllStatuses(int $year): array
	{
		return array_map(
			fn(LegalEntity $e) => $this->buildStatus($e, $year),
			$this->legalEntityRepository->findActive(),
		);
	}

	private function resolveLimit(LegalEntity $entity, int $year): ?string
	{
		if ($entity->getEpGroup() === null) {
			return null;
		}

		$rateSet = $this->taxRateSetRepository->findByYear($year);
		if ($rateSet === null) {
			return null;
		}

		return match($entity->getEpGroup()) {
			1 => $rateSet->getGroup1IncomeLimit(),
			2 => $rateSet->getGroup2IncomeLimit(),
			3 => $rateSet->getGroup3IncomeLimit(),
			default => null,
		};
	}
}

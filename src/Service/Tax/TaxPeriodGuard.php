<?php

namespace App\Service\Tax;

use App\Entity\LegalEntity;
use App\Entity\TaxReportingPeriod;
use App\Repository\TaxReportingPeriodRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Protects income data in closed/declared reporting periods.
 *
 * Manual income mutations must call assertOpen() and surface the error to the
 * user. Automatic payment recognition must use isClosed() and skip silently —
 * the payment flow is never blocked by tax-period state.
 */
final class TaxPeriodGuard
{
	public function __construct(
		private readonly TaxReportingPeriodRepository $periodRepository,
	)
	{
	}

	public function findBlockingPeriod(LegalEntity $entity, DateTimeImmutable $date): ?TaxReportingPeriod
	{
		return $this->periodRepository->findBlockingPeriod($entity, $date);
	}

	public function isClosed(LegalEntity $entity, DateTimeImmutable $date): bool
	{
		return $this->findBlockingPeriod($entity, $date) instanceof TaxReportingPeriod;
	}

	public function assertOpen(LegalEntity $entity, DateTimeImmutable $date): void
	{
		$period = $this->findBlockingPeriod($entity, $date);

		if ($period instanceof TaxReportingPeriod) {
			throw new RuntimeException(sprintf(
				'Reporting period %s — %s is %s. Reopen the period before changing income in it.',
				$period->getDateFrom()?->format('d.m.Y'),
				$period->getDateTo()?->format('d.m.Y'),
				$period->getStatus()->value,
			));
		}
	}
}

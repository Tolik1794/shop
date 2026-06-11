<?php

namespace App\Manager;

use App\Entity\TaxReportingPeriod;
use App\Entity\User\User;
use App\Enum\TaxPeriodStatusEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class TaxPeriodManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly UserManager $userManager,
	)
	{
	}

	public function close(TaxReportingPeriod $period): void
	{
		if ($period->getStatus() !== TaxPeriodStatusEnum::OPEN) {
			throw new RuntimeException('Only an open period can be closed.');
		}

		$this->transition($period, TaxPeriodStatusEnum::CLOSED, null);
	}

	public function markDeclared(TaxReportingPeriod $period): void
	{
		if ($period->getStatus() !== TaxPeriodStatusEnum::CLOSED) {
			throw new RuntimeException('Only a closed period can be marked as declared.');
		}

		$this->transition($period, TaxPeriodStatusEnum::DECLARED, null);
	}

	public function reopen(TaxReportingPeriod $period, string $comment): void
	{
		if ($period->getStatus() === TaxPeriodStatusEnum::OPEN) {
			throw new RuntimeException('Period is already open.');
		}

		if (trim($comment) === '') {
			throw new RuntimeException('Reopening a period requires a comment.');
		}

		$this->transition($period, TaxPeriodStatusEnum::OPEN, $comment);
	}

	private function transition(TaxReportingPeriod $period, TaxPeriodStatusEnum $to, ?string $comment): void
	{
		$actor = $this->userManager->getCurrentUser();

		$log = $period->getStatusLog() ?? [];
		$log[] = [
			'at'      => (new DateTimeImmutable())->format(DATE_ATOM),
			'by'      => $actor instanceof User ? $actor->getId() : null,
			'from'    => $period->getStatus()->value,
			'to'      => $to->value,
			'comment' => $comment,
		];

		$period
			->setStatus($to)
			->setStatusLog($log)
			->setUpdatedAt(new DateTimeImmutable());

		$this->em->flush();
	}
}

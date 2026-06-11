<?php

namespace App\Manager;

use App\Entity\TaxAccrual;
use App\Entity\User\User;
use App\Enum\TaxAccrualStatusEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class TaxAccrualManager
{
	public function __construct(
		private readonly EntityManagerInterface $em,
	) {}

	public function markPaid(
		TaxAccrual $accrual,
		DateTimeImmutable $paidAt,
		string $paidAmount,
	): void {
		$accrual
			->setStatus(TaxAccrualStatusEnum::PAID)
			->setPaidAmount($paidAmount)
			->setPaidAt($paidAt)
			->setUpdatedAt(new DateTimeImmutable());

		$this->em->flush();
	}

	public function reopen(TaxAccrual $accrual): void
	{
		$accrual
			->setStatus(TaxAccrualStatusEnum::ACCRUED)
			->setPaidAt(null)
			->setUpdatedAt(new DateTimeImmutable());

		$this->em->flush();
	}
}

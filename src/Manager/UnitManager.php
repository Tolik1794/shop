<?php

namespace App\Manager;

use App\Entity\Unit;
use App\Enum\ActiveStatusEnum;
use App\Repository\UnitRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class UnitManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): UnitRepository
	{
		return $this->entityManager->getRepository(Unit::class);
	}

	public function archive(Unit $unit): void
	{
		$now = new DateTimeImmutable();

		$unit
			->setStatus(ActiveStatusEnum::INACTIVE)
			->setDeletedAt($now)
			->setUpdatedAt($now);

		$this->save($unit);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

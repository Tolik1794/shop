<?php

namespace App\Manager;

use App\Entity\Unit;
use App\Repository\UnitRepository;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use Doctrine\ORM\EntityManagerInterface;

class UnitManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ReferenceArchivePolicy $referenceArchivePolicy,
	)
	{
	}

	public function getRepository(): UnitRepository
	{
		return $this->entityManager->getRepository(Unit::class);
	}

	public function archive(Unit $unit): void
	{
		$this->referenceArchivePolicy->archive($unit);
		$this->save($unit);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

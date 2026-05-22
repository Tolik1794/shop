<?php

namespace App\Manager;

use App\Entity\InventoryReason;
use App\Repository\InventoryReasonRepository;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use Doctrine\ORM\EntityManagerInterface;

class InventoryReasonManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ReferenceArchivePolicy $referenceArchivePolicy,
	)
	{
	}

	public function getRepository(): InventoryReasonRepository
	{
		return $this->entityManager->getRepository(InventoryReason::class);
	}

	public function archive(InventoryReason $inventoryReason): void
	{
		$this->referenceArchivePolicy->archive($inventoryReason);
		$this->save($inventoryReason);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

<?php

namespace App\Manager;

use App\Entity\InventoryReason;
use App\Enum\ActiveStatusEnum;
use App\Repository\InventoryReasonRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class InventoryReasonManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): InventoryReasonRepository
	{
		return $this->entityManager->getRepository(InventoryReason::class);
	}

	public function archive(InventoryReason $inventoryReason): void
	{
		$now = new DateTimeImmutable();

		$inventoryReason
			->setStatus(ActiveStatusEnum::INACTIVE)
			->setDeletedAt($now)
			->setUpdatedAt($now);

		$this->save($inventoryReason);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

<?php

namespace App\Manager;

use App\Entity\Warehouse;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;

class WarehouseManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): WarehouseRepository
	{
		return $this->entityManager->getRepository(Warehouse::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

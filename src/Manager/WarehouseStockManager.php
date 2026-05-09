<?php

namespace App\Manager;

use App\Entity\WarehouseStock;
use App\Repository\WarehouseStockRepository;
use Doctrine\ORM\EntityManagerInterface;

class WarehouseStockManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): WarehouseStockRepository
	{
		return $this->entityManager->getRepository(WarehouseStock::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

<?php

namespace App\Manager;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SupplierManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): SupplierRepository
	{
		return $this->entityManager->getRepository(Supplier::class);
	}

	public function softDelete(Supplier $supplier): void
	{
		$supplier->setDeletedAt(new DateTimeImmutable());
		$this->save($supplier);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

<?php

namespace App\Manager;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use Doctrine\ORM\EntityManagerInterface;

class SupplierManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ReferenceArchivePolicy $referenceArchivePolicy,
	)
	{
	}

	public function getRepository(): SupplierRepository
	{
		return $this->entityManager->getRepository(Supplier::class);
	}

	public function softDelete(Supplier $supplier): void
	{
		$this->referenceArchivePolicy->archive($supplier);
		$this->save($supplier);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

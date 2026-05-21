<?php

namespace App\Manager;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use Doctrine\ORM\EntityManagerInterface;

class CustomerManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ReferenceArchivePolicy $referenceArchivePolicy,
	)
	{
	}

	public function getRepository(): CustomerRepository
	{
		return $this->entityManager->getRepository(Customer::class);
	}

	public function softDelete(Customer $customer): void
	{
		$this->referenceArchivePolicy->archive($customer);
		$this->save($customer);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

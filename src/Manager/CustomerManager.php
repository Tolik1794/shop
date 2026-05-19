<?php

namespace App\Manager;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class CustomerManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): CustomerRepository
	{
		return $this->entityManager->getRepository(Customer::class);
	}

	public function softDelete(Customer $customer): void
	{
		$customer->setDeletedAt(new DateTimeImmutable());
		$this->save($customer);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

<?php

namespace App\Manager;

use App\Exception\ConcurrencyConflictException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectRepository;

abstract class AbstractManager
{
	abstract public function getEntityManager(): EntityManagerInterface;

	abstract public function getRepository(): ObjectRepository;

	public function findOneBy(array $criteria, ?array $orderBy = null, ?int $limit = null, $offset = null): mixed
	{
		return $this->getRepository()->findOneBy($criteria, $orderBy);
	}

	public function findAll(): array
	{
		return $this->getRepository()->findAll();
	}

	public function find($id, $lockMode = null, $lockVersion = null): mixed
	{
		return $this->getRepository()->find($id, $lockMode, $lockVersion);
	}

	public function save(object $entity): void
	{
		$this->getEntityManager()->persist($entity);
		try {
			$this->getEntityManager()->flush();
		} catch (OptimisticLockException) {
			throw new ConcurrencyConflictException();
		}
	}

	public function delete(object $entity): void
	{
		$this->getEntityManager()->remove($entity);
		$this->getEntityManager()->flush();
	}

}

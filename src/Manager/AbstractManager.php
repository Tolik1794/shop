<?php

namespace App\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

abstract class AbstractManager
{
	public function __construct(public readonly EntityManagerInterface $entityManager)
	{
	}

	/**
	 * @param array      $criteria
	 * @param array|null $orderBy
	 * @param int|null   $limit
	 * @param            $offset
	 * @return mixed
	 */
	public function findOneBy(array $criteria, ?array $orderBy = null, ?int $limit = null, $offset = null): mixed
	{
		return $this->getRepository()->findOneBy($criteria, $orderBy);
	}

	/**
	 * @return array
	 */
	public function findAll(): array
	{
		return $this->getRepository()->findAll();
	}

	/**
	 * @param $id
	 * @param $lockMode
	 * @param $lockVersion
	 * @return mixed
	 */
	public function find($id, $lockMode = null, $lockVersion = null): mixed
	{
		return $this->getRepository()->find($id, $lockMode, $lockVersion);
	}

	/**
	 * @param object $entity
	 * @return void
	 */
	public function save(object $entity): void
	{
		$this->entityManager->persist($entity);
		$this->entityManager->flush();
	}

	/**
	 * @param object $entity
	 * @return void
	 */
	public function delete(object $entity): void
	{
		$this->entityManager->remove($entity);
		$this->entityManager->flush();
	}

	/**
	 * @return ObjectRepository
	 */
	public abstract function getRepository(): ObjectRepository;
}
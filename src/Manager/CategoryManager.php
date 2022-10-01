<?php

namespace App\Manager;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): CategoryRepository
	{
		return $this->entityManager->getRepository(Category::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}
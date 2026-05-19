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

	public function save(object $entity): void
	{
		if ($entity instanceof Category) {
			$parent = $entity->getParent();
			$entity->setLevel($parent ? $parent->getLevel() + 1 : 1);
		}

		parent::save($entity);
	}
}

<?php

namespace App\Manager;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): ProductRepository
	{
		return $this->entityManager->getRepository(Product::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}
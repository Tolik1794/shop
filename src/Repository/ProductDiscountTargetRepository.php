<?php

namespace App\Repository;

use App\Entity\ProductDiscountTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductDiscountTarget>
 */
class ProductDiscountTargetRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductDiscountTarget::class);
	}
}

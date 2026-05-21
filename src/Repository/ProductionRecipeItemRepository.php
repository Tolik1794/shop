<?php

namespace App\Repository;

use App\Entity\ProductionRecipeItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductionRecipeItem>
 *
 * @method ProductionRecipeItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionRecipeItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionRecipeItem[]    findAll()
 * @method ProductionRecipeItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionRecipeItemRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductionRecipeItem::class);
	}
}

<?php

namespace App\Repository;

use App\Entity\ProductionOrderMaterial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductionOrderMaterial>
 *
 * @method ProductionOrderMaterial|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionOrderMaterial|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionOrderMaterial[]    findAll()
 * @method ProductionOrderMaterial[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionOrderMaterialRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductionOrderMaterial::class);
	}
}

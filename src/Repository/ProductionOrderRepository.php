<?php

namespace App\Repository;

use App\Entity\ProductionOrder;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductionOrder>
 *
 * @method ProductionOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionOrder[]    findAll()
 * @method ProductionOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionOrderRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductionOrder::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('productionOrder')
			->innerJoin('productionOrder.product', 'product')
			->addSelect('product')
			->leftJoin('productionOrder.warehouse', 'warehouse')
			->addSelect('warehouse')
			->leftJoin('productionOrder.recipe', 'recipe')
			->addSelect('recipe')
			->leftJoin('productionOrder.materials', 'material')
			->addSelect('material')
			->leftJoin('material.material', 'materialProduct')
			->addSelect('materialProduct')
			->andWhere('productionOrder.store = :store')
			->setParameter('store', $store);
	}

	public function getIndexPage(ProductionOrder $productionOrder, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('productionOrder')
			->select('COUNT(productionOrder.id)')
			->andWhere('productionOrder.store = :store')
			->andWhere('productionOrder.id >= :id')
			->setParameter('store', $productionOrder->getStore())
			->setParameter('id', $productionOrder->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}
}

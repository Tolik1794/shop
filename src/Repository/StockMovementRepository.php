<?php

namespace App\Repository;

use App\Entity\StockMovement;
use App\Entity\WarehouseStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMovement>
 *
 * @method StockMovement|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockMovement|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockMovement[]    findAll()
 * @method StockMovement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockMovementRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, StockMovement::class);
	}

	/**
	 * @return StockMovement[]
	 */
	public function findByWarehouseStockInPostingOrder(WarehouseStock $warehouseStock): array
	{
		return $this->createQueryBuilder('stockMovement')
			->andWhere('stockMovement.warehouseStock = :warehouseStock')
			->setParameter('warehouseStock', $warehouseStock)
			->orderBy('stockMovement.createdAt', 'ASC')
			->addOrderBy('stockMovement.id', 'ASC')
			->getQuery()
			->getResult();
	}
}

<?php

namespace App\Repository;

use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarehouseStockBatch>
 */
class WarehouseStockBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseStockBatch::class);
    }

	/**
	 * @return WarehouseStockBatch[]
	 */
	public function findByWarehouseStock(WarehouseStock $warehouseStock): array
	{
		return $this->createQueryBuilder('warehouseStockBatch')
			->leftJoin('warehouseStockBatch.purchaseEntry', 'purchaseEntry')
			->addSelect('purchaseEntry')
			->andWhere('warehouseStockBatch.warehouseStock = :warehouseStock')
			->setParameter('warehouseStock', $warehouseStock)
			->orderBy('warehouseStockBatch.receivedAt', 'DESC')
			->addOrderBy('warehouseStockBatch.id', 'DESC')
			->getQuery()
			->getResult();
	}

	/**
	 * @return WarehouseStockBatch[]
	 */
	public function findOpenByWarehouseStock(WarehouseStock $warehouseStock): array
	{
		return $this->createQueryBuilder('warehouseStockBatch')
			->andWhere('warehouseStockBatch.warehouseStock = :warehouseStock')
			->andWhere('warehouseStockBatch.remainingQuantity > 0')
			->setParameter('warehouseStock', $warehouseStock)
			->orderBy('warehouseStockBatch.receivedAt', 'ASC')
			->addOrderBy('warehouseStockBatch.id', 'ASC')
			->getQuery()
			->getResult();
	}

	public function sumOpenRemainingByWarehouseStock(WarehouseStock $warehouseStock): string
	{
		$value = $this->createQueryBuilder('warehouseStockBatch')
			->select('COALESCE(SUM(warehouseStockBatch.remainingQuantity), 0)')
			->andWhere('warehouseStockBatch.warehouseStock = :warehouseStock')
			->andWhere('warehouseStockBatch.remainingQuantity > 0')
			->setParameter('warehouseStock', $warehouseStock)
			->getQuery()
			->getSingleScalarResult();

		return number_format((float) $value, 4, '.', '');
	}

    //    /**
    //     * @return WarehouseStockBatch[] Returns an array of WarehouseStockBatch objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('w.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?WarehouseStockBatch
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

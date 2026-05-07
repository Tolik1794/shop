<?php

namespace App\Repository;

use App\Entity\WarehouseStock;
use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WarehouseStock>
 *
 * @method WarehouseStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method WarehouseStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method WarehouseStock[]    findAll()
 * @method WarehouseStock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WarehouseStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseStock::class);
    }

    public function add(WarehouseStock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WarehouseStock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAvailableByWarehouseQB(Warehouse $warehouse): QueryBuilder
	{
		return $this->createQueryBuilder('warehouseStock')
			->innerJoin('warehouseStock.product', 'product')
			->addSelect('product')
			->where('warehouseStock.warehouse = :warehouse')
			->setParameter('warehouse', $warehouse);
	}

//    /**
//     * @return WarehouseStock[] Returns an array of WarehouseStock objects
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

//    public function findOneBySomeField($value): ?WarehouseStock
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

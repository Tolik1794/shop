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

	public function existsForProductAndWarehouse(WarehouseStock $warehouseStock): bool
	{
		if (!$warehouseStock->getWarehouse() || !$warehouseStock->getProduct()) {
			return false;
		}

		$qb = $this->createQueryBuilder('warehouseStock')
			->select('COUNT(warehouseStock.id)')
			->andWhere('warehouseStock.warehouse = :warehouse')
			->andWhere('warehouseStock.product = :product')
			->setParameter('warehouse', $warehouseStock->getWarehouse())
			->setParameter('product', $warehouseStock->getProduct());

		if ($warehouseStock->getId()) {
			$qb->andWhere('warehouseStock.id != :id')
				->setParameter('id', $warehouseStock->getId());
		}

		return (int) $qb->getQuery()->getSingleScalarResult() > 0;
	}

	public function getIndexPage(WarehouseStock $warehouseStock, int $limit = 20): int
	{
		$product = $warehouseStock->getProduct();

		$count = (int) $this->createQueryBuilder('warehouseStock')
			->select('COUNT(warehouseStock.id)')
			->innerJoin('warehouseStock.product', 'product')
			->andWhere('warehouseStock.warehouse = :warehouse')
			->andWhere('product.name < :productName OR (product.name = :productName AND warehouseStock.id <= :id)')
			->setParameter('warehouse', $warehouseStock->getWarehouse())
			->setParameter('productName', $product?->getName())
			->setParameter('id', $warehouseStock->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}
}

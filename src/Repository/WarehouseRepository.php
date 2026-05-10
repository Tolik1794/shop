<?php

namespace App\Repository;

use App\Entity\Warehouse;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Warehouse>
 *
 * @method Warehouse|null find($id, $lockMode = null, $lockVersion = null)
 * @method Warehouse|null findOneBy(array $criteria, array $orderBy = null)
 * @method Warehouse[]    findAll()
 * @method Warehouse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WarehouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warehouse::class);
    }

    public function add(Warehouse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Warehouse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('warehouse')
			->innerJoin('warehouse.store', 'store')
			->where('store = :store')
			->setParameter('store', $store);
	}

	public function getIndexPage(Warehouse $warehouse, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('warehouse')
			->select('COUNT(warehouse.id)')
			->andWhere('warehouse.store = :store')
			->andWhere('warehouse.name > :name OR (warehouse.name = :name AND warehouse.id >= :id)')
			->setParameter('store', $warehouse->getStore())
			->setParameter('name', $warehouse->getName())
			->setParameter('id', $warehouse->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}
}

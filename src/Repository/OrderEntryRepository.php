<?php

namespace App\Repository;

use App\Entity\OrderEntry;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderEntry>
 *
 * @method OrderEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderEntry[]    findAll()
 * @method OrderEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderEntry::class);
    }

    public function add(OrderEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findReturnableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('orderEntry')
			->innerJoin('orderEntry.order', 'orders')
			->addSelect('orders')
			->innerJoin('orderEntry.product', 'product')
			->addSelect('product')
			->leftJoin('orderEntry.warehouse', 'warehouse')
			->addSelect('warehouse')
			->andWhere('orders.store = :store')
			->andWhere('COALESCE(orderEntry.shippedQuantity, 0) > COALESCE(orderEntry.returnedQuantity, 0)')
			->setParameter('store', $store)
			->orderBy('orders.id', 'DESC')
			->addOrderBy('orderEntry.id', 'ASC');
	}

//    /**
//     * @return OrderEntry[] Returns an array of OrderEntry objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?OrderEntry
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

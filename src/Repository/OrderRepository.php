<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function add(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('orders')
			->leftJoin('orders.customer', 'customer')
			->addSelect('customer')
			->innerJoin('orders.currency', 'currency')
			->addSelect('currency')
			->leftJoin('orders.orderEntries', 'orderEntry')
			->addSelect('orderEntry')
			->andWhere('orders.store = :store')
			->setParameter('store', $store);
	}

	public function getNextNumber(Store $store): string
	{
		$count = (int) $this->createQueryBuilder('orders')
			->select('COUNT(orders.id)')
			->andWhere('orders.store = :store')
			->setParameter('store', $store)
			->getQuery()
			->getSingleScalarResult();

		return sprintf('SO-%d-%06d', $store->getId(), $count + 1);
	}

	public function getIndexPage(Order $order, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('orders')
			->select('COUNT(orders.id)')
			->andWhere('orders.store = :store')
			->andWhere('orders.id >= :id')
			->setParameter('store', $order->getStore())
			->setParameter('id', $order->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}

//    /**
//     * @return Order[] Returns an array of Order objects
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

//    public function findOneBySomeField($value): ?Order
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

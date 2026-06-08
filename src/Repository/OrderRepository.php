<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderStatus;
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

	/**
	 * Orders waiting for stock in a store that contain at least one of the given products. Oldest first so
	 * scarce replenished stock is reserved for earlier orders. Used to re-evaluate availability when stock
	 * arrives (see {@see \App\Service\Order\AwaitingStockReplenishmentService}).
	 *
	 * @param int[] $productIds
	 *
	 * @return Order[]
	 */
	public function findAwaitingStockByProducts(int $storeId, array $productIds): array
	{
		if ($productIds === []) {
			return [];
		}

		return $this->createQueryBuilder('orders')
			->innerJoin('orders.orderEntries', 'orderEntry')
			->andWhere('orders.store = :storeId')
			->andWhere('orders.status = :status')
			->andWhere('orderEntry.product IN (:productIds)')
			->setParameter('storeId', $storeId)
			->setParameter('status', OrderStatus::AWAITING_STOCK)
			->setParameter('productIds', $productIds)
			->groupBy('orders.id')
			->orderBy('orders.id', 'ASC')
			->getQuery()
			->getResult();
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

	/**
	 * Recent orders for a customer, newest first. Used by the quick customer history panel.
	 *
	 * @return Order[]
	 */
	public function findRecentByCustomer(Customer $customer, int $limit = 10): array
	{
		return $this->createQueryBuilder('orders')
			->innerJoin('orders.currency', 'currency')
			->addSelect('currency')
			->andWhere('orders.customer = :customer')
			->setParameter('customer', $customer)
			->orderBy('orders.createdAt', 'DESC')
			->addOrderBy('orders.id', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
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

	/**
	 * @return Order[]
	 */
	public function findChoicesByStoreAndSearch(Store $store, string $search, int $limit = 20): array
	{
		return $this->createQueryBuilder('orders')
			->andWhere('orders.store = :store')
			->andWhere('orders.number LIKE :search OR orders.customerNameSnapshot LIKE :search')
			->setParameter('store', $store)
			->setParameter('search', '%' . $search . '%')
			->orderBy('orders.id', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
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

<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderComment>
 */
class OrderCommentRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, OrderComment::class);
	}

	/**
	 * @return OrderComment[]
	 */
	public function findVisibleByOrder(Order $order): array
	{
		return $this->createQueryBuilder('orderComment')
			->innerJoin('orderComment.author', 'author')
			->addSelect('author')
			->andWhere('orderComment.order = :order')
			->andWhere('orderComment.deletedAt IS NULL')
			->setParameter('order', $order)
			->orderBy('orderComment.createdAt', 'DESC')
			->addOrderBy('orderComment.id', 'DESC')
			->getQuery()
			->getResult();
	}

	/**
	 * Important, non-deleted comments across all of a customer's orders, newest first.
	 *
	 * @return OrderComment[]
	 */
	public function findImportantByCustomer(Customer $customer, int $limit = 10): array
	{
		return $this->createQueryBuilder('orderComment')
			->innerJoin('orderComment.author', 'author')
			->addSelect('author')
			->innerJoin('orderComment.order', 'orders')
			->addSelect('orders')
			->andWhere('orders.customer = :customer')
			->andWhere('orderComment.isImportant = true')
			->andWhere('orderComment.deletedAt IS NULL')
			->setParameter('customer', $customer)
			->orderBy('orderComment.createdAt', 'DESC')
			->addOrderBy('orderComment.id', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}
}

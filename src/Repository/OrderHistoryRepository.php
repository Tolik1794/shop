<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderHistory>
 */
class OrderHistoryRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, OrderHistory::class);
	}

	/**
	 * @return OrderHistory[]
	 */
	public function findTimelineByOrder(Order $order): array
	{
		return $this->createQueryBuilder('orderHistory')
			->leftJoin('orderHistory.actor', 'actor')
			->addSelect('actor')
			->andWhere('orderHistory.order = :order')
			->setParameter('order', $order)
			->orderBy('orderHistory.occurredAt', 'DESC')
			->addOrderBy('orderHistory.id', 'DESC')
			->getQuery()
			->getResult();
	}
}

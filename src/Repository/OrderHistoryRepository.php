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

	public function findLatestStatusChangeToStatus(Order $order, string $status): ?OrderHistory
	{
		$historyEntries = $this->createQueryBuilder('orderHistory')
			->andWhere('orderHistory.order = :order')
			->andWhere('orderHistory.eventKey = :eventKey')
			->setParameter('order', $order)
			->setParameter('eventKey', 'order.status_changed')
			->orderBy('orderHistory.occurredAt', 'DESC')
			->addOrderBy('orderHistory.id', 'DESC')
			->getQuery()
			->getResult();

		foreach ($historyEntries as $historyEntry) {
			$statusChange = $historyEntry->getChanges()['status'] ?? null;

			if (!is_array($statusChange)) {
				continue;
			}

			if (
				($statusChange['to'] ?? null) === $status
				&& is_string($statusChange['from'] ?? null)
				&& $statusChange['from'] !== $status
			) {
				return $historyEntry;
			}
		}

		return null;
	}
}

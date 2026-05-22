<?php

namespace App\Repository;

use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StatusHistory>
 */
class StatusHistoryRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, StatusHistory::class);
	}

	/**
	 * @return StatusHistory[]
	 */
	public function findTimelineFor(Store $store, StatusHistoryEntityType $entityType, int $entityId): array
	{
		return $this->createQueryBuilder('statusHistory')
			->leftJoin('statusHistory.changedBy', 'changedBy')
			->addSelect('changedBy')
			->andWhere('statusHistory.store = :store')
			->andWhere('statusHistory.entityType = :entityType')
			->andWhere('statusHistory.entityId = :entityId')
			->setParameter('store', $store)
			->setParameter('entityType', $entityType)
			->setParameter('entityId', $entityId)
			->orderBy('statusHistory.changedAt', 'DESC')
			->addOrderBy('statusHistory.id', 'DESC')
			->getQuery()
			->getResult();
	}

	public function hasTimelineFor(Store $store, StatusHistoryEntityType $entityType, int $entityId): bool
	{
		return (bool) $this->createQueryBuilder('statusHistory')
			->select('1')
			->andWhere('statusHistory.store = :store')
			->andWhere('statusHistory.entityType = :entityType')
			->andWhere('statusHistory.entityId = :entityId')
			->setParameter('store', $store)
			->setParameter('entityType', $entityType)
			->setParameter('entityId', $entityId)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}
}

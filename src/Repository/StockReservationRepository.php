<?php

namespace App\Repository;

use App\Entity\OrderEntry;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\WarehouseStockBatch;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockReservation>
 *
 * @method StockReservation|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockReservation|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockReservation[]    findAll()
 * @method StockReservation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockReservationRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, StockReservation::class);
	}

	/**
	 * @return StockReservation[]
	 */
	public function findActiveForOrderEntry(OrderEntry $orderEntry): array
	{
		return $this->findBy([
			'orderEntry' => $orderEntry,
			'status' => StockReservationStatus::ACTIVE,
		]);
	}

	public function getActiveQuantityForOrderEntry(OrderEntry $orderEntry): string
	{
		$quantity = $this->createQueryBuilder('stockReservation')
			->select('COALESCE(SUM(stockReservation.quantity), 0)')
			->andWhere('stockReservation.orderEntry = :orderEntry')
			->andWhere('stockReservation.status = :status')
			->setParameter('orderEntry', $orderEntry)
			->setParameter('status', StockReservationStatus::ACTIVE)
			->getQuery()
			->getSingleScalarResult();

		return number_format((float) $quantity, 4, '.', '');
	}

	public function getActiveQuantityForBatch(WarehouseStockBatch $batch): string
	{
		$quantity = $this->createQueryBuilder('stockReservation')
			->select('COALESCE(SUM(stockReservation.quantity), 0)')
			->andWhere('stockReservation.warehouseStockBatch = :batch')
			->andWhere('stockReservation.status = :status')
			->setParameter('batch', $batch)
			->setParameter('status', StockReservationStatus::ACTIVE)
			->getQuery()
			->getSingleScalarResult();

		return number_format((float) $quantity, 4, '.', '');
	}

	/**
	 * @return StockReservation[]
	 */
	public function findExpiredActiveReservations(DateTimeImmutable $now): array
	{
		return $this->createQueryBuilder('stockReservation')
			->andWhere('stockReservation.status = :status')
			->andWhere('stockReservation.expiresAt IS NOT NULL')
			->andWhere('stockReservation.expiresAt <= :now')
			->setParameter('status', StockReservationStatus::ACTIVE)
			->setParameter('now', $now)
			->getQuery()
			->getResult();
	}
}

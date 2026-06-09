<?php

namespace App\Repository;

use App\Entity\OrderEntry;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\WarehouseStock;
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
		if ($orderEntry->getId() === null) {
			return '0.0000';
		}

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
		return $this->getQuantityForBatch($batch, StockReservationStatus::ACTIVE);
	}

	public function getActiveQuantityForBatchAndOrderEntry(WarehouseStockBatch $batch, OrderEntry $orderEntry): string
	{
		if ($batch->getId() === null || $orderEntry->getId() === null) {
			return '0.0000';
		}

		$quantity = $this->createQueryBuilder('stockReservation')
			->select('COALESCE(SUM(stockReservation.quantity), 0)')
			->andWhere('stockReservation.warehouseStockBatch = :batch')
			->andWhere('stockReservation.orderEntry = :orderEntry')
			->andWhere('stockReservation.status = :status')
			->setParameter('batch', $batch)
			->setParameter('orderEntry', $orderEntry)
			->setParameter('status', StockReservationStatus::ACTIVE)
			->getQuery()
			->getSingleScalarResult();

		return number_format((float) $quantity, 4, '.', '');
	}

	public function getQuantityForOrderEntryByStatus(OrderEntry $orderEntry, StockReservationStatus $status): string
	{
		$quantity = $this->createQueryBuilder('stockReservation')
			->select('COALESCE(SUM(stockReservation.quantity), 0)')
			->andWhere('stockReservation.orderEntry = :orderEntry')
			->andWhere('stockReservation.status = :status')
			->setParameter('orderEntry', $orderEntry)
			->setParameter('status', $status)
			->getQuery()
			->getSingleScalarResult();

		return number_format((float) $quantity, 4, '.', '');
	}

	/**
	 * @return OrderEntry[]
	 */
	public function findOrderEntriesWithActiveReservations(?int $storeId = null, ?int $orderId = null): array
	{
		$queryBuilder = $this->createQueryBuilder('stockReservation')
			->innerJoin('stockReservation.orderEntry', 'orderEntry')
			->addSelect('orderEntry')
			->innerJoin('orderEntry.order', 'orders')
			->addSelect('orders')
			->andWhere('stockReservation.status = :status')
			->setParameter('status', StockReservationStatus::ACTIVE)
			->orderBy('orderEntry.id', 'ASC');

		if ($storeId !== null) {
			$queryBuilder
				->andWhere('IDENTITY(orders.store) = :storeId')
				->setParameter('storeId', $storeId);
		}

		if ($orderId !== null) {
			$queryBuilder
				->andWhere('orders.id = :orderId')
				->setParameter('orderId', $orderId);
		}

		$entries = [];
		foreach ($queryBuilder->getQuery()->getResult() as $reservation) {
			$entry = $reservation->getOrderEntry();
			if ($entry instanceof OrderEntry && $entry->getId() !== null) {
				$entries[$entry->getId()] = $entry;
			}
		}

		return array_values($entries);
	}

	public function getActiveQuantityForWarehouseStock(WarehouseStock $warehouseStock): string
	{
		$quantity = $this->createQueryBuilder('stockReservation')
			->select('COALESCE(SUM(stockReservation.quantity), 0)')
			->andWhere('stockReservation.warehouseStock = :warehouseStock')
			->andWhere('stockReservation.status = :status')
			->setParameter('warehouseStock', $warehouseStock)
			->setParameter('status', StockReservationStatus::ACTIVE)
			->getQuery()
			->getSingleScalarResult();

		return number_format((float) $quantity, 4, '.', '');
	}

	private function getQuantityForBatch(WarehouseStockBatch $batch, StockReservationStatus $status): string
	{
		if ($batch->getId() === null) {
			return '0.0000';
		}

		$quantity = $this->createQueryBuilder('stockReservation')
			->select('COALESCE(SUM(stockReservation.quantity), 0)')
			->andWhere('stockReservation.warehouseStockBatch = :batch')
			->andWhere('stockReservation.status = :status')
			->setParameter('batch', $batch)
			->setParameter('status', $status)
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

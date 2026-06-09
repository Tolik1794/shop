<?php

namespace App\Service;

use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Exception\StockOperationException;
use App\Repository\StockReservationRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class StockReservationService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly StockReservationRepository $stockReservationRepository,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	public function reserve(OrderEntry $orderEntry, WarehouseStock $warehouseStock, string $quantity, ?DateTimeImmutable $expiresAt = null): StockReservation
	{
		return $this->reserveWithBatch($orderEntry, $warehouseStock, $orderEntry->getWarehouseStockBatch(), $quantity, $expiresAt);
	}

	public function reserveBatch(OrderEntry $orderEntry, WarehouseStockBatch $batch, string $quantity, ?DateTimeImmutable $expiresAt = null): StockReservation
	{
		$warehouseStock = $batch->getWarehouseStock();
		if (!$warehouseStock instanceof WarehouseStock) {
			throw new StockOperationException('Reservation batch has no warehouse stock.');
		}

		return $this->reserveWithBatch($orderEntry, $warehouseStock, $batch, $quantity, $expiresAt);
	}

	private function reserveWithBatch(
		OrderEntry $orderEntry,
		WarehouseStock $warehouseStock,
		?WarehouseStockBatch $batch,
		string $quantity,
		?DateTimeImmutable $expiresAt,
	): StockReservation
	{
		if (!$this->entityManager->getConnection()->isTransactionActive()) {
			return $this->entityManager->wrapInTransaction(
				fn (): StockReservation => $this->reserveWithBatch($orderEntry, $warehouseStock, $batch, $quantity, $expiresAt)
			);
		}

		$this->assertPositiveQuantity($quantity);
		$this->assertReservationMatchesOrderEntry($orderEntry, $warehouseStock);
		$this->lockPersisted($warehouseStock);
		if ($batch instanceof WarehouseStockBatch) {
			$this->assertBatchMatchesWarehouseStock($batch, $warehouseStock);
			$this->lockPersisted($batch);
		}

		if ((float) $quantity > (float) $this->getAvailableQuantity($warehouseStock)) {
			throw new StockOperationException('Reserved quantity cannot be greater than available quantity.');
		}

		if ($batch instanceof WarehouseStockBatch && (float) $quantity > (float) $this->getAvailableBatchQuantity($batch)) {
			throw new StockOperationException('Reserved batch quantity cannot be greater than available batch quantity.');
		}

		$reservation = (new StockReservation())
			->setOrderEntry($orderEntry)
			->setWarehouseStock($warehouseStock)
			->setWarehouseStockBatch($batch)
			->setQuantity($this->normalize($quantity))
			->setExpiresAt($expiresAt);

		$orderEntry->addStockReservation($reservation);
		$warehouseStock->addStockReservation($reservation);
		$warehouseStock->setReservedQuantity($this->add($warehouseStock->getReservedQuantity(), $quantity));

		$this->entityManager->persist($reservation);
		$this->entityManager->flush();

		return $reservation;
	}

	public function reserveForOrderEntry(OrderEntry $orderEntry, WarehouseStock $warehouseStock): ?StockReservation
	{
		$remainingQuantity = $this->subtract(
			$orderEntry->getQuantity(),
			$this->stockReservationRepository->getActiveQuantityForOrderEntry($orderEntry)
		);

		if ((float) $remainingQuantity <= 0) {
			return null;
		}

		$availableQuantity = $this->getAvailableQuantity($warehouseStock);
		$store = $orderEntry->getOrder()?->getStore();
		$quantityToReserve = $remainingQuantity;

		if ($store?->isAllowBackorders() && (float) $remainingQuantity > (float) $availableQuantity) {
			$quantityToReserve = $availableQuantity;
		}

		if ((float) $quantityToReserve <= 0) {
			return null;
		}

		return $this->reserve($orderEntry, $warehouseStock, $quantityToReserve);
	}

	public function release(StockReservation $reservation): void
	{
		$this->close($reservation, StockReservationStatus::CANCELED);
	}

	public function complete(StockReservation $reservation): void
	{
		$this->close($reservation, StockReservationStatus::COMPLETED);
	}

	public function completeForOrderEntry(OrderEntry $orderEntry, string $quantity, bool $flush = true, bool $lock = true): void
	{
		if (!$this->entityManager->getConnection()->isTransactionActive()) {
			$this->entityManager->wrapInTransaction(function () use ($orderEntry, $quantity, $flush, $lock): void {
				$this->completeForOrderEntry($orderEntry, $quantity, $flush, $lock);
			});

			return;
		}

		$remainingQuantity = $this->normalize($quantity);
		$this->assertPositiveQuantity($remainingQuantity);
		$reservations = $this->stockReservationRepository->findActiveForOrderEntry($orderEntry);
		if ($lock) {
			$this->lockReservationTargets($reservations);
		}

		foreach ($reservations as $reservation) {
			if ((float) $remainingQuantity <= 0) {
				break;
			}

			$remainingQuantity = $this->completeReservedQuantity($reservation, $remainingQuantity, false);
		}

		if ($flush) {
			$this->entityManager->flush();
		}
	}

	/**
	 * Releases (cancels) up to $quantity of the still-active reservations of an order entry. Used when
	 * a customer refuses part of a not-yet-shipped position so the reserved stock becomes available
	 * again. Releasing less than is active leaves the rest reserved for the position.
	 */
	public function releaseForOrderEntry(OrderEntry $orderEntry, string $quantity, bool $flush = true): void
	{
		if (!$this->entityManager->getConnection()->isTransactionActive()) {
			$this->entityManager->wrapInTransaction(function () use ($orderEntry, $quantity, $flush): void {
				$this->releaseForOrderEntry($orderEntry, $quantity, $flush);
			});

			return;
		}

		$remainingQuantity = $this->normalize($quantity);
		$this->assertPositiveQuantity($remainingQuantity);
		$reservations = $this->stockReservationRepository->findActiveForOrderEntry($orderEntry);
		$this->lockReservationTargets($reservations);

		foreach ($reservations as $reservation) {
			if ((float) $remainingQuantity <= 0) {
				break;
			}

			$remainingQuantity = $this->releaseReservedQuantity($reservation, $remainingQuantity, false);
		}

		if ($flush) {
			$this->entityManager->flush();
		}
	}

	public function expireOldReservations(DateTimeImmutable $now): int
	{
		if (!$this->entityManager->getConnection()->isTransactionActive()) {
			return $this->entityManager->wrapInTransaction(fn (): int => $this->expireOldReservations($now));
		}

		$reservations = $this->stockReservationRepository->findExpiredActiveReservations($now);
		$this->lockReservationTargets($reservations);

		foreach ($reservations as $reservation) {
			$this->close($reservation, StockReservationStatus::EXPIRED, false, false);
		}

		$this->entityManager->flush();

		return count($reservations);
	}

	public function getActiveQuantityForOrderEntry(OrderEntry $orderEntry): string
	{
		return $this->stockReservationRepository->getActiveQuantityForOrderEntry($orderEntry);
	}

	private function close(StockReservation $reservation, StockReservationStatus $status, bool $flush = true, bool $lock = true): void
	{
		if (!$this->entityManager->getConnection()->isTransactionActive()) {
			$this->entityManager->wrapInTransaction(function () use ($reservation, $status, $flush, $lock): void {
				$this->close($reservation, $status, $flush, $lock);
			});

			return;
		}

		if ($reservation->getStatus() !== StockReservationStatus::ACTIVE) {
			return;
		}

		$warehouseStock = $reservation->getWarehouseStock();
		if (!$warehouseStock instanceof WarehouseStock) {
			throw new StockOperationException('Reservation has no warehouse stock.');
		}
		$batch = $reservation->getWarehouseStockBatch();
		if ($lock) {
			$this->lockPersisted($warehouseStock);
			if ($batch instanceof WarehouseStockBatch) {
				$this->lockPersisted($batch);
			}
		}

		$newReservedQuantity = $this->subtract($warehouseStock->getReservedQuantity(), $reservation->getQuantity());
		if ((float) $newReservedQuantity < 0) {
			throw new StockOperationException('Reserved quantity cannot be negative.');
		}

		$reservation->setStatus($status);
		$warehouseStock->setReservedQuantity($newReservedQuantity);

		if ($flush) {
			$this->entityManager->flush();
		}
	}

	private function completeReservedQuantity(StockReservation $reservation, string $quantityToComplete, bool $lock = true): string
	{
		$warehouseStock = $reservation->getWarehouseStock();
		$orderEntry = $reservation->getOrderEntry();
		if (!$warehouseStock instanceof WarehouseStock || !$orderEntry instanceof OrderEntry) {
			throw new StockOperationException('Reservation must have order entry and warehouse stock.');
		}

		$reservationQuantity = $this->normalize($reservation->getQuantity());
		if ((float) $quantityToComplete >= (float) $reservationQuantity) {
			$this->close($reservation, StockReservationStatus::COMPLETED, false, $lock);

			return $this->subtract($quantityToComplete, $reservationQuantity);
		}

		$completedReservation = (new StockReservation())
			->setOrderEntry($orderEntry)
			->setWarehouseStock($warehouseStock)
			->setWarehouseStockBatch($reservation->getWarehouseStockBatch())
			->setQuantity($quantityToComplete)
			->setStatus(StockReservationStatus::COMPLETED)
			->setReservedAt($reservation->getReservedAt())
			->setExpiresAt($reservation->getExpiresAt());

		$reservation->setQuantity($this->subtract($reservationQuantity, $quantityToComplete));
		$warehouseStock->setReservedQuantity($this->subtract($warehouseStock->getReservedQuantity(), $quantityToComplete));
		$orderEntry->addStockReservation($completedReservation);
		$warehouseStock->addStockReservation($completedReservation);
		$this->entityManager->persist($completedReservation);

		return '0.0000';
	}

	private function releaseReservedQuantity(StockReservation $reservation, string $quantityToRelease, bool $lock = true): string
	{
		$reservationQuantity = $this->normalize($reservation->getQuantity());

		// Releasing the whole reservation reuses close(), which also revalidates stock and status.
		if ((float) $quantityToRelease >= (float) $reservationQuantity) {
			$this->close($reservation, StockReservationStatus::CANCELED, false, $lock);

			return $this->subtract($quantityToRelease, $reservationQuantity);
		}

		$warehouseStock = $reservation->getWarehouseStock();
		if (!$warehouseStock instanceof WarehouseStock) {
			throw new StockOperationException('Reservation has no warehouse stock.');
		}

		$batch = $reservation->getWarehouseStockBatch();
		if ($lock) {
			$this->lockPersisted($warehouseStock);
			if ($batch instanceof WarehouseStockBatch) {
				$this->lockPersisted($batch);
			}
		}

		$newReservedQuantity = $this->subtract($warehouseStock->getReservedQuantity(), $quantityToRelease);
		if ((float) $newReservedQuantity < 0) {
			throw new StockOperationException('Reserved quantity cannot be negative.');
		}

		$reservation->setQuantity($this->subtract($reservationQuantity, $quantityToRelease));
		$warehouseStock->setReservedQuantity($newReservedQuantity);

		return '0.0000';
	}

	/**
	 * @param list<StockReservation> $reservations
	 */
	private function lockReservationTargets(array $reservations): void
	{
		$targets = [];

		foreach ($reservations as $reservation) {
			$warehouseStock = $reservation->getWarehouseStock();
			if ($warehouseStock instanceof WarehouseStock) {
				$targets[$this->entityKey($warehouseStock)] = $warehouseStock;
			}

			$batch = $reservation->getWarehouseStockBatch();
			if ($batch instanceof WarehouseStockBatch) {
				$targets[$this->entityKey($batch)] = $batch;
			}
		}

		$this->concurrencyGuard->lockAll(array_values($targets));
	}

	private function entityKey(object $entity): string
	{
		$identifier = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);

		return $identifier === []
			? sprintf('%s:object:%d', $entity::class, spl_object_id($entity))
			: sprintf('%s:id:%s', $entity::class, implode(':', array_map('strval', $identifier)));
	}

	private function assertReservationMatchesOrderEntry(OrderEntry $orderEntry, WarehouseStock $warehouseStock): void
	{
		if (!$orderEntry->getProduct() instanceof Product || $orderEntry->getProduct() !== $warehouseStock->getProduct()) {
			throw new StockOperationException('Reservation product must match order entry product.');
		}

		if ($orderEntry->getWarehouse() !== $warehouseStock->getWarehouse()) {
			throw new StockOperationException('Reservation warehouse must match order entry warehouse.');
		}
	}

	private function lockPersisted(object $entity): void
	{
		if ($this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity) !== []) {
			$this->concurrencyGuard->lock($entity);
		}
	}

	private function assertBatchMatchesWarehouseStock(WarehouseStockBatch $batch, WarehouseStock $warehouseStock): void
	{
		if ($batch->getWarehouseStock() !== $warehouseStock) {
			throw new StockOperationException('Reservation batch must match warehouse stock.');
		}
	}

	private function assertPositiveQuantity(string $quantity): void
	{
		if ((float) $quantity <= 0) {
			throw new StockOperationException('Reservation quantity must be greater than zero.');
		}
	}

	private function getAvailableQuantity(WarehouseStock $warehouseStock): string
	{
		return $this->normalize(max(0, (float) $this->subtract($warehouseStock->getQuantityOnHand(), $warehouseStock->getReservedQuantity())));
	}

	private function getAvailableBatchQuantity(WarehouseStockBatch $batch): string
	{
		return $this->normalize(max(
			0,
			(float) $this->subtract($batch->getRemainingQuantity(), $this->stockReservationRepository->getActiveQuantityForBatch($batch))
		));
	}

	private function add(?string $left, ?string $right): string
	{
		return $this->normalize((float) $left + (float) $right);
	}

	private function subtract(?string $left, ?string $right): string
	{
		return $this->normalize((float) $left - (float) $right);
	}

	private function normalize(float|string $quantity): string
	{
		return number_format((float) $quantity, 4, '.', '');
	}
}

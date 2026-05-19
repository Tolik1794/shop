<?php

namespace App\Service;

use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\WarehouseStock;
use App\Exception\StockOperationException;
use App\Repository\StockReservationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class StockReservationService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly StockReservationRepository $stockReservationRepository,
	)
	{
	}

	public function reserve(OrderEntry $orderEntry, WarehouseStock $warehouseStock, string $quantity, ?DateTimeImmutable $expiresAt = null): StockReservation
	{
		$this->assertPositiveQuantity($quantity);
		$this->assertReservationMatchesOrderEntry($orderEntry, $warehouseStock);

		if ((float) $quantity > (float) $this->getAvailableQuantity($warehouseStock)) {
			throw new StockOperationException('Reserved quantity cannot be greater than available quantity.');
		}

		$reservation = (new StockReservation())
			->setOrderEntry($orderEntry)
			->setWarehouseStock($warehouseStock)
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

	public function expireOldReservations(DateTimeImmutable $now): int
	{
		$count = 0;

		foreach ($this->stockReservationRepository->findExpiredActiveReservations($now) as $reservation) {
			$this->close($reservation, StockReservationStatus::EXPIRED, false);
			$count++;
		}

		$this->entityManager->flush();

		return $count;
	}

	public function getActiveQuantityForOrderEntry(OrderEntry $orderEntry): string
	{
		return $this->stockReservationRepository->getActiveQuantityForOrderEntry($orderEntry);
	}

	private function close(StockReservation $reservation, StockReservationStatus $status, bool $flush = true): void
	{
		if ($reservation->getStatus() !== StockReservationStatus::ACTIVE) {
			return;
		}

		$warehouseStock = $reservation->getWarehouseStock();
		if (!$warehouseStock instanceof WarehouseStock) {
			throw new StockOperationException('Reservation has no warehouse stock.');
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

	private function assertReservationMatchesOrderEntry(OrderEntry $orderEntry, WarehouseStock $warehouseStock): void
	{
		if (!$orderEntry->getProduct() instanceof Product || $orderEntry->getProduct() !== $warehouseStock->getProduct()) {
			throw new StockOperationException('Reservation product must match order entry product.');
		}

		if ($orderEntry->getWarehouse() !== $warehouseStock->getWarehouse()) {
			throw new StockOperationException('Reservation warehouse must match order entry warehouse.');
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

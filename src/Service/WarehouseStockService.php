<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Exception\StockOperationException;
use App\Repository\WarehouseStockRepository;
use Doctrine\ORM\EntityManagerInterface;

class WarehouseStockService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly WarehouseStockRepository $warehouseStockRepository,
	)
	{
	}

	public function findOrCreate(Warehouse $warehouse, Product $product): WarehouseStock
	{
		$warehouseStock = $this->warehouseStockRepository->findOneBy([
			'warehouse' => $warehouse,
			'product' => $product,
		]);

		if ($warehouseStock instanceof WarehouseStock) {
			return $warehouseStock;
		}

		$warehouseStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product);

		$this->entityManager->persist($warehouseStock);

		return $warehouseStock;
	}

	public function increase(Warehouse $warehouse, Product $product, string $quantity, ?string $averageCost = null): WarehouseStock
	{
		$this->assertNonNegativeQuantity($quantity);

		$warehouseStock = $this->findOrCreate($warehouse, $product);
		$warehouseStock->setQuantityOnHand($this->add($warehouseStock->getQuantityOnHand(), $quantity));

		if ($averageCost !== null) {
			$this->assertNonNegativeQuantity($averageCost);
			$warehouseStock->setAverageCost($averageCost);
		}

		$this->entityManager->flush();

		return $warehouseStock;
	}

	public function decrease(Warehouse $warehouse, Product $product, string $quantity): WarehouseStock
	{
		$this->assertNonNegativeQuantity($quantity);

		$warehouseStock = $this->findOrCreate($warehouse, $product);
		$newQuantityOnHand = $this->subtract($warehouseStock->getQuantityOnHand(), $quantity);

		if ($this->isNegative($newQuantityOnHand)) {
			throw new StockOperationException('Quantity on hand cannot be negative.');
		}

		$this->assertReservationAllowed($warehouseStock, $newQuantityOnHand, $warehouseStock->getReservedQuantity());

		$warehouseStock->setQuantityOnHand($newQuantityOnHand);
		$this->entityManager->flush();

		return $warehouseStock;
	}

	public function reserve(Warehouse $warehouse, Product $product, string $quantity): WarehouseStock
	{
		$this->assertNonNegativeQuantity($quantity);

		$warehouseStock = $this->findOrCreate($warehouse, $product);
		$newReservedQuantity = $this->add($warehouseStock->getReservedQuantity(), $quantity);

		$this->assertReservationAllowed($warehouseStock, $warehouseStock->getQuantityOnHand(), $newReservedQuantity);

		$warehouseStock->setReservedQuantity($newReservedQuantity);
		$this->entityManager->flush();

		return $warehouseStock;
	}

	public function release(Warehouse $warehouse, Product $product, string $quantity): WarehouseStock
	{
		$this->assertNonNegativeQuantity($quantity);

		$warehouseStock = $this->findOrCreate($warehouse, $product);
		$newReservedQuantity = $this->subtract($warehouseStock->getReservedQuantity(), $quantity);

		if ($this->isNegative($newReservedQuantity)) {
			throw new StockOperationException('Reserved quantity cannot be negative.');
		}

		$warehouseStock->setReservedQuantity($newReservedQuantity);
		$this->entityManager->flush();

		return $warehouseStock;
	}

	private function assertReservationAllowed(WarehouseStock $warehouseStock, string $quantityOnHand, string $reservedQuantity): void
	{
		if ((float) $reservedQuantity > (float) $quantityOnHand) {
			throw new StockOperationException('Reserved quantity cannot be greater than quantity on hand.');
		}
	}

	private function assertNonNegativeQuantity(string $quantity): void
	{
		if ($this->isNegative($quantity)) {
			throw new StockOperationException('Quantity cannot be negative.');
		}
	}

	private function add(?string $left, string $right): string
	{
		return number_format((float) $left + (float) $right, 4, '.', '');
	}

	private function subtract(?string $left, string $right): string
	{
		return number_format((float) $left - (float) $right, 4, '.', '');
	}

	private function isNegative(string $quantity): bool
	{
		return (float) $quantity < 0;
	}
}

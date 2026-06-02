<?php

namespace App\Service\Discount;

use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\WarehouseStockBatch;
use App\Repository\WarehouseStockRepository;

class DiscountCostBasisCalculator
{
	public function __construct(private readonly WarehouseStockRepository $warehouseStockRepository)
	{
	}

	public function forOrderEntry(OrderEntry $orderEntry): DiscountCostBasis
	{
		$batch = $orderEntry->getWarehouseStockBatch();
		if ($batch instanceof WarehouseStockBatch && $batch->getUnitCost() !== null) {
			return new DiscountCostBasis($this->formatMoney((float) $batch->getUnitCost()));
		}

		$product = $orderEntry->getProduct();
		$store = $orderEntry->getOrder()?->getStore();

		if (!$product instanceof Product || !$store instanceof Store) {
			return new DiscountCostBasis(null);
		}

		return $this->forProduct($product, $store);
	}

	public function forProduct(Product $product, Store $store): DiscountCostBasis
	{
		return new DiscountCostBasis($this->weightedAverageCost($product, $store));
	}

	private function weightedAverageCost(Product $product, Store $store): ?string
	{
		$totalQuantity = 0.0;
		$totalCost = 0.0;
		$rows = $this->warehouseStockRepository->createQueryBuilder('stock')
			->select('stock.quantityOnHand AS quantityOnHand', 'stock.averageCost AS averageCost')
			->innerJoin('stock.warehouse', 'warehouse')
			->andWhere('stock.product = :product')
			->andWhere('warehouse.store = :store')
			->andWhere('stock.quantityOnHand > 0')
			->andWhere('stock.averageCost > 0')
			->setParameter('product', $product)
			->setParameter('store', $store)
			->getQuery()
			->getArrayResult();

		foreach ($rows as $row) {
			$quantity = max(0.0, $this->numberValue($row['quantityOnHand'] ?? null));
			$averageCost = max(0.0, $this->numberValue($row['averageCost'] ?? null));

			if ($quantity <= 0.00005 || $averageCost <= 0.00005) {
				continue;
			}

			$totalQuantity += $quantity;
			$totalCost += $quantity * $averageCost;
		}

		if ($totalQuantity <= 0.00005) {
			return null;
		}

		return $this->formatMoney($totalCost / $totalQuantity);
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

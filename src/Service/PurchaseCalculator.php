<?php

namespace App\Service;

use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use DateTimeImmutable;

/**
 * Calculates purchase landed costs.
 *
 * Delivery is distributed proportionally by line merchandise value. The final
 * line receives any rounding remainder so entry allocations add up exactly to
 * the header delivery cost.
 */
class PurchaseCalculator
{
	public function apply(Purchase $purchase): void
	{
		$entries = array_values(array_filter(
			$purchase->getPurchaseEntries()->toArray(),
			static fn (mixed $entry): bool => $entry instanceof PurchaseEntry,
		));
		$exchangeRateToBase = $this->numberValue($purchase->getExchangeRateToBase());
		$deliveryCost = max(0, $this->numberValue($purchase->getDeliveryCost()));
		$merchandiseTotals = [];
		$merchandiseTotal = 0.0;

		foreach ($entries as $entry) {
			$lineTotal = max(0, $this->numberValue($entry->getQuantity()) * $this->numberValue($entry->getUnitCost()));
			$merchandiseTotals[] = $lineTotal;
			$merchandiseTotal += $lineTotal;
		}

		$allocatedDelivery = 0.0;
		$totalAmount = 0.0;
		$totalAmountBase = 0.0;

		foreach ($entries as $index => $entry) {
			$lineMerchandiseTotal = $merchandiseTotals[$index] ?? 0.0;
			$lineDeliveryCost = $this->allocateDeliveryCost(
				index: $index,
				entryCount: count($entries),
				lineMerchandiseTotal: $lineMerchandiseTotal,
				merchandiseTotal: $merchandiseTotal,
				deliveryCost: $deliveryCost,
				alreadyAllocated: $allocatedDelivery,
			);
			$allocatedDelivery += $lineDeliveryCost;
			$lineTotal = $lineMerchandiseTotal + $lineDeliveryCost;
			$lineTotalBase = $lineTotal * $exchangeRateToBase;

			$entry
				->setUnitCostBase($this->formatMoney($this->numberValue($entry->getUnitCost()) * $exchangeRateToBase))
				->setDeliveryCost($lineDeliveryCost > 0 ? $this->formatMoney($lineDeliveryCost) : null)
				->setDeliveryCostBase($lineDeliveryCost > 0 ? $this->formatMoney($lineDeliveryCost * $exchangeRateToBase) : null)
				->setTotalCost($this->formatMoney($lineTotal))
				->setTotalCostBase($this->formatMoney($lineTotalBase))
				->setSalePriceBase($entry->getSalePrice() !== null
					? $this->formatMoney($this->numberValue($entry->getSalePrice()) * $exchangeRateToBase)
					: null);

			$totalAmount += $lineTotal;
			$totalAmountBase += $lineTotalBase;
		}

		$purchase
			->setDeliveryCost($deliveryCost > 0 ? $this->formatMoney($deliveryCost) : null)
			->setDeliveryCostBase($deliveryCost > 0 ? $this->formatMoney($deliveryCost * $exchangeRateToBase) : null)
			->setTotalAmount($this->formatMoney($totalAmount))
			->setTotalAmountBase($this->formatMoney($totalAmountBase))
			->setUpdatedAt(new DateTimeImmutable());
	}

	private function allocateDeliveryCost(
		int $index,
		int $entryCount,
		float $lineMerchandiseTotal,
		float $merchandiseTotal,
		float $deliveryCost,
		float $alreadyAllocated,
	): float {
		if ($deliveryCost <= 0 || $entryCount === 0 || $merchandiseTotal <= 0) {
			return 0.0;
		}

		if ($index === $entryCount - 1) {
			return max(0, $this->roundMoney($deliveryCost - $alreadyAllocated));
		}

		return $this->roundMoney($deliveryCost * ($lineMerchandiseTotal / $merchandiseTotal));
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function roundMoney(float $value): float
	{
		return round($value, 4);
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

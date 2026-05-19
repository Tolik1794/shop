<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderEntry;
use DateTimeImmutable;

class OrderCalculator
{
	public function calculateOrder(Order $order): array
	{
		$entries = [];

		foreach ($order->getOrderEntries() as $index => $orderEntry) {
			$entries[$index] = [
				'product' => $orderEntry->getProduct()?->getId(),
				'quantity' => $orderEntry->getQuantity(),
				'unitPrice' => $orderEntry->getUnitPrice(),
				'discount' => $orderEntry->getDiscountAmount(),
			];
		}

		return $this->calculateEntries($entries, (float) $order->getExchangeRateToBase());
	}

	public function calculatePayload(array $payload): array
	{
		return $this->calculateEntries($payload['orderEntries'] ?? [], displayMoney: true);
	}

	public function apply(Order $order): void
	{
		$summary = [
			'total' => 0.0,
			'totalBase' => 0.0,
			'discount' => 0.0,
			'discountBase' => 0.0,
		];
		$exchangeRateToBase = (float) $order->getExchangeRateToBase();

		foreach ($order->getOrderEntries() as $orderEntry) {
			$entrySummary = $this->calculateEntry([
				'product' => $orderEntry->getProduct()?->getId(),
				'quantity' => $orderEntry->getQuantity(),
				'unitPrice' => $orderEntry->getUnitPrice(),
				'discount' => $orderEntry->getDiscountAmount(),
			], $exchangeRateToBase);

			$orderEntry
				->setUnitPriceBase($entrySummary['unitPriceBase'])
				->setDiscountAmountBase((float) $entrySummary['discountBase'] > 0 ? $entrySummary['discountBase'] : null)
				->setTotalPrice($entrySummary['total'])
				->setTotalPriceBase($entrySummary['totalBase']);

			$summary['total'] += (float) $entrySummary['total'];
			$summary['totalBase'] += (float) $entrySummary['totalBase'];
			$summary['discount'] += (float) $entrySummary['discount'];
			$summary['discountBase'] += (float) $entrySummary['discountBase'];
		}

		$order
			->setTotalAmount($this->formatMoney($summary['total']))
			->setTotalAmountBase($this->formatMoney($summary['totalBase']))
			->setDiscountAmount($summary['discount'] > 0 ? $this->formatMoney($summary['discount']) : null)
			->setDiscountAmountBase($summary['discountBase'] > 0 ? $this->formatMoney($summary['discountBase']) : null)
			->setUpdatedAt(new DateTimeImmutable());
	}

	private function calculateEntries(iterable $entries, float $exchangeRateToBase = 1.0, bool $displayMoney = false): array
	{
		$count = 0;
		$subtotal = 0.0;
		$discount = 0.0;
		$total = 0.0;
		$lineTotals = [];
		$formatter = $displayMoney ? $this->formatDisplayMoney(...) : $this->formatMoney(...);

		foreach ($entries as $key => $entry) {
			if (!is_array($entry) || (empty($entry['product']) && !$this->hasCalculationValues($entry))) {
				continue;
			}

			$entrySummary = $this->calculateEntry($entry, $exchangeRateToBase);

			$count++;
			$subtotal += (float) $entrySummary['subtotal'];
			$discount += (float) $entrySummary['discount'];
			$total += (float) $entrySummary['total'];
			$lineTotals[$key] = $formatter((float) $entrySummary['total']);
		}

		return [
			'count' => $count,
			'subtotal' => $formatter($subtotal),
			'discount' => $formatter($discount),
			'total' => $formatter($total),
			'lineTotals' => $lineTotals,
		];
	}

	private function calculateEntry(array $entry, float $exchangeRateToBase): array
	{
		$quantity = $this->numberValue($entry['quantity'] ?? 0);
		$unitPrice = $this->numberValue($entry['unitPrice'] ?? 0);
		$discount = max(0, $this->numberValue($entry['discountAmount'] ?? $entry['discount'] ?? 0));
		$subtotal = max(0, $quantity * $unitPrice);
		$total = max(0, $subtotal - $discount);

		return [
			'subtotal' => $this->formatMoney($subtotal),
			'discount' => $this->formatMoney($discount),
			'total' => $this->formatMoney($total),
			'unitPriceBase' => $this->formatMoney($unitPrice * $exchangeRateToBase),
			'discountBase' => $this->formatMoney($discount * $exchangeRateToBase),
			'totalBase' => $this->formatMoney($total * $exchangeRateToBase),
		];
	}

	private function hasCalculationValues(array $entry): bool
	{
		return $this->numberValue($entry['quantity'] ?? 0) > 0
			|| $this->numberValue($entry['unitPrice'] ?? 0) > 0
			|| $this->numberValue($entry['discountAmount'] ?? $entry['discount'] ?? 0) > 0;
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

	private function formatDisplayMoney(float $value): string
	{
		return number_format($value, 2, '.', '');
	}
}

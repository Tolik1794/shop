<?php

namespace App\Service\Quantity;

use App\Entity\Product;
use App\Entity\Unit;

final readonly class QuantityFormatter
{
	public const STORAGE_SCALE = 4;

	public function precisionForProduct(?Product $product): int
	{
		return $this->precisionForUnit($product?->getUnit());
	}

	public function precisionForUnit(?Unit $unit): int
	{
		return $this->normalizePrecision($unit?->getPrecision());
	}

	public function normalizePrecision(?int $precision): int
	{
		return min(self::STORAGE_SCALE, max(0, (int) ($precision ?? self::STORAGE_SCALE)));
	}

	public function stepForPrecision(int $precision): string
	{
		$precision = $this->normalizePrecision($precision);

		if ($precision === 0) {
			return '1';
		}

		return '0.' . str_repeat('0', $precision - 1) . '1';
	}

	public function format(mixed $quantity, Product|Unit|null $source = null): string
	{
		if ($quantity === null || $quantity === '') {
			return '';
		}

		$precision = $source instanceof Product
			? $this->precisionForProduct($source)
			: $this->precisionForUnit($source);

		return $this->formatForPrecision($quantity, $precision);
	}

	public function formatForPrecision(mixed $quantity, int $precision): string
	{
		return number_format($this->numberValue($quantity), $this->normalizePrecision($precision), '.', '');
	}

	public function formatForForm(mixed $quantity, Product|Unit|null $source = null): string|int|null
	{
		if ($quantity === null || $quantity === '') {
			return null;
		}

		$precision = $source instanceof Product
			? $this->precisionForProduct($source)
			: $this->precisionForUnit($source);

		return $precision === 0
			? (int) $this->numberValue($quantity)
			: $this->formatForPrecision($quantity, $precision);
	}

	public function formatForStorage(mixed $quantity): string
	{
		if ($quantity === null || $quantity === '') {
			return $this->formatForPrecision(0, self::STORAGE_SCALE);
		}

		return $this->formatForPrecision($quantity, self::STORAGE_SCALE);
	}

	private function numberValue(mixed $quantity): float
	{
		return (float) str_replace(',', '.', (string) $quantity);
	}
}

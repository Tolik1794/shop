<?php

namespace App\Workflow\History;

class HistoryValueComparator
{
	/**
	 * @param string[] $decimalFields
	 */
	public function hasSameValue(string $field, mixed $before, mixed $after, array $decimalFields = []): bool
	{
		if ($before === $after) {
			return true;
		}

		if (!in_array($field, $decimalFields, true)) {
			return false;
		}

		$beforeDecimal = $this->normalizeDecimal($before);
		$afterDecimal = $this->normalizeDecimal($after);

		return $beforeDecimal !== null && $beforeDecimal === $afterDecimal;
	}

	public function normalizeDecimal(mixed $value): ?string
	{
		if (!is_int($value) && !is_float($value) && !is_string($value)) {
			return null;
		}

		$value = trim((string) $value);

		if (!preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
			return null;
		}

		$isNegative = str_starts_with($value, '-');
		$value = ltrim($value, '+-');
		[$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
		$integer = ltrim($integer, '0');
		$fraction = rtrim($fraction, '0');
		$integer = $integer !== '' ? $integer : '0';

		if ($integer === '0' && $fraction === '') {
			return '0';
		}

		return ($isNegative ? '-' : '') . $integer . ($fraction !== '' ? '.' . $fraction : '');
	}
}

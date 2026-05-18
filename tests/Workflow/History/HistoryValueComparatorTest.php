<?php

namespace App\Tests\Workflow\History;

use App\Workflow\History\HistoryValueComparator;
use PHPUnit\Framework\TestCase;

class HistoryValueComparatorTest extends TestCase
{
	public function testEquivalentDecimalValuesAreTreatedAsEqual(): void
	{
		$comparator = new HistoryValueComparator();

		self::assertTrue($comparator->hasSameValue('amount', '001.2300', '1.23', ['amount']));
		self::assertTrue($comparator->hasSameValue('amount', '-0.0000', '0', ['amount']));
		self::assertFalse($comparator->hasSameValue('amount', '1.23', '1.24', ['amount']));
	}

	public function testNonDecimalFieldsUseStrictComparison(): void
	{
		$comparator = new HistoryValueComparator();

		self::assertTrue($comparator->hasSameValue('status', 'draft', 'draft'));
		self::assertFalse($comparator->hasSameValue('status', '1', 1));
	}

	public function testDecimalNormalizationRejectsInvalidValues(): void
	{
		$comparator = new HistoryValueComparator();

		self::assertNull($comparator->normalizeDecimal('1,23'));
		self::assertNull($comparator->normalizeDecimal(null));
	}
}

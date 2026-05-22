<?php

namespace App\Tests\Service\Quantity;

use App\Entity\Product;
use App\Entity\Unit;
use App\Service\Quantity\QuantityFormatter;
use PHPUnit\Framework\TestCase;

class QuantityFormatterTest extends TestCase
{
	private QuantityFormatter $formatter;

	protected function setUp(): void
	{
		$this->formatter = new QuantityFormatter();
	}

	public function testFormatsQuantityWithUnitPrecision(): void
	{
		self::assertSame('12', $this->formatter->format('12.3400', $this->productWithPrecision(0)));
		self::assertSame('12.3', $this->formatter->format('12.3400', $this->productWithPrecision(1)));
		self::assertSame('12.340', $this->formatter->format('12.3400', $this->productWithPrecision(3)));
	}

	public function testBuildsStepFromPrecision(): void
	{
		self::assertSame('1', $this->formatter->stepForPrecision(0));
		self::assertSame('0.01', $this->formatter->stepForPrecision(2));
		self::assertSame('0.0001', $this->formatter->stepForPrecision(4));
	}

	public function testFormValueUsesIntegerForZeroPrecision(): void
	{
		self::assertSame(7, $this->formatter->formatForForm('7.9000', $this->productWithPrecision(0)));
		self::assertSame('7.90', $this->formatter->formatForForm('7.9000', $this->productWithPrecision(2)));
	}

	public function testStorageKeepsExistingDatabaseScale(): void
	{
		self::assertSame('7.0000', $this->formatter->formatForStorage(7));
		self::assertSame('7.1250', $this->formatter->formatForStorage('7.125'));
	}

	private function productWithPrecision(int $precision): Product
	{
		return (new Product())->setUnit((new Unit())->setCode('u')->setPrecision($precision));
	}
}

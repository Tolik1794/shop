<?php

namespace App\Tests\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\TestCase;

class MoneyExtensionTest extends TestCase
{
	public function testFormatsMoneyWithGroupedThousands(): void
	{
		$extension = new MoneyExtension();

		self::assertSame('1 000 000.00', $extension->formatMoney('1000000.00'));
		self::assertSame('1 000 000.00', $extension->formatMoney('1 000 000.00'));
		self::assertSame('12 345.68', $extension->formatMoney('12345.678'));
		self::assertSame('0.00', $extension->formatMoney(null));
	}
}

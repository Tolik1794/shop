<?php

namespace App\Tests\Enum;

use App\Enum\ActiveStatusEnum;
use PHPUnit\Framework\TestCase;

class ActiveStatusEnumTest extends TestCase
{
	public function testUserSelectableCasesExcludeDeleted(): void
	{
		self::assertSame([
			ActiveStatusEnum::ACTIVE,
			ActiveStatusEnum::INACTIVE,
		], ActiveStatusEnum::userSelectableCases());
	}
}

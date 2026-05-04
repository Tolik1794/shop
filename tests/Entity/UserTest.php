<?php

namespace App\Tests\Entity;

use App\Entity\User\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
	public function testNewUserHasNoDatabaseIdBeforePersist(): void
	{
		self::assertNull((new User())->getId());
	}

	public function testUserIdentifierUsesEmail(): void
	{
		$user = (new User())
			->setEmail('user@example.com')
			->setNickname('nickname');

		self::assertSame('user@example.com', $user->getUserIdentifier());
	}
}

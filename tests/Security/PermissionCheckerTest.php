<?php

namespace App\Tests\Security;

use App\Entity\Permission;
use App\Entity\User\User;
use App\Entity\User\UserGroup;
use App\Entity\User\UserPermissionOverride;
use App\Enum\PermissionOverrideEffect;
use App\Security\PermissionCatalog;
use App\Security\PermissionChecker;
use PHPUnit\Framework\TestCase;

class PermissionCheckerTest extends TestCase
{
	public function testGroupPermissionGrantsAccess(): void
	{
		$user = new User();
		$user->addGroup((new UserGroup())->addPermission($this->permission('dashboard.view')));

		self::assertTrue($this->checker()->isGranted($user, 'dashboard.view'));
		self::assertFalse($this->checker()->isGranted($user, 'dashboard.financial'));
	}

	public function testIndividualDenyOverridesGroupAllow(): void
	{
		$user = new User();
		$user->addGroup((new UserGroup())->addPermission($this->permission('dashboard.financial')));
		$user->addPermissionOverride((new UserPermissionOverride())
			->setPermission($this->permission('dashboard.financial'))
			->setEffect(PermissionOverrideEffect::DENY));

		self::assertFalse($this->checker()->isGranted($user, 'dashboard.financial'));
	}

	public function testLegacySuperAdminRoleKeepsExistingTestsWorking(): void
	{
		$user = (new User())->setRoles(['ROLE_SUPER_ADMIN']);

		self::assertTrue($this->checker()->isGranted($user, 'order.edit'));
	}

	private function checker(): PermissionChecker
	{
		return new PermissionChecker(new PermissionCatalog());
	}

	private function permission(string $code): Permission
	{
		[$category, $name, $description] = PermissionCatalog::PERMISSIONS[$code];

		return (new Permission())
			->setCode($code)
			->setCategory($category)
			->setName($name)
			->setDescription($description);
	}
}

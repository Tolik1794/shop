<?php

namespace App\Security;

use App\Entity\User\User;
use App\Enum\PermissionOverrideEffect;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class PermissionChecker
{
	public function __construct(private PermissionCatalog $permissionCatalog)
	{
	}

	public function isGranted(User|UserInterface|null $user, string $permissionCode): bool
	{
		if (!$user instanceof User || !$this->permissionCatalog->has($permissionCode)) {
			return false;
		}

		try {
			if ($user->hasPermissionOverride($permissionCode, PermissionOverrideEffect::DENY)) {
				return false;
			}

			if ($user->hasPermissionOverride($permissionCode, PermissionOverrideEffect::ALLOW)
				|| $user->hasPermissionOverride(PermissionCatalog::SYSTEM_ALL, PermissionOverrideEffect::ALLOW)
			) {
				return true;
			}

			$groupPermissionCodes = $this->groupPermissionCodes($user);
		} catch (TableNotFoundException) {
			return $this->legacyRoleFallbackIsGranted($user, $permissionCode);
		}

		return in_array(PermissionCatalog::SYSTEM_ALL, $groupPermissionCodes, true)
			|| in_array($permissionCode, $groupPermissionCodes, true);
	}

	public function groupPermissionCodes(User $user): array
	{
		$permissionCodes = [];

		foreach ($user->getGroups() as $group) {
			foreach ($group->getPermissions() as $permission) {
				$permissionCodes[] = $permission->getCode();
			}
		}

		if ($permissionCodes === []) {
			foreach ($this->permissionCatalog->legacyRoleGroupCodes($user->getLegacyRoles()) as $groupCode) {
				$permissionCodes = array_merge($permissionCodes, $this->permissionCatalog->groupPermissions($groupCode));
			}
		}

		return array_values(array_unique($permissionCodes));
	}

	private function legacyRoleFallbackIsGranted(User $user, string $permissionCode): bool
	{
		$permissionCodes = [];

		foreach ($this->permissionCatalog->legacyRoleGroupCodes($user->getLegacyRoles()) as $groupCode) {
			$permissionCodes = array_merge($permissionCodes, $this->permissionCatalog->groupPermissions($groupCode));
		}

		return in_array(PermissionCatalog::SYSTEM_ALL, $permissionCodes, true)
			|| in_array($permissionCode, $permissionCodes, true);
	}
}

<?php

namespace App\DataFixtures;

use App\Entity\Permission;
use App\Entity\User\UserGroup;
use App\Security\PermissionCatalog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PermissionFixtures extends Fixture
{
	public function __construct(private readonly PermissionCatalog $permissionCatalog)
	{
	}

	public function load(ObjectManager $manager): void
	{
		$permissionEntities = [];
		$sortOrder = 10;

		foreach ($this->permissionCatalog->permissions() as $code => [$category, $name, $description]) {
			$permission = $manager->getRepository(Permission::class)->findOneBy(['code' => $code]) ?? new Permission();
			$permission
				->setCode($code)
				->setCategory($category)
				->setName($name)
				->setDescription($description)
				->setSortOrder($sortOrder)
				->touch();

			$manager->persist($permission);
			$permissionEntities[$code] = $permission;
			$sortOrder += 10;
		}

		foreach (PermissionCatalog::GROUPS as $code => [$name, $description, $system]) {
			$group = $manager->getRepository(UserGroup::class)->findOneBy(['code' => $code]) ?? new UserGroup();
			$group
				->setCode($code)
				->setName($name)
				->setDescription($description)
				->setSystem($system)
				->setPermissions(array_map(
					static fn (string $permissionCode): Permission => $permissionEntities[$permissionCode],
					$this->permissionCatalog->groupPermissions($code),
				))
				->touch();

			$manager->persist($group);
			$this->addReference('user_group.' . $code, $group);
		}

		$manager->flush();
	}
}

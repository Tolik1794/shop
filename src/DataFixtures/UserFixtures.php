<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
	const ROLES = [
		'ROLE_SUPER_ADMIN' => 'Super admin',
		'ROLE_ADMIN' => 'Admin',
		'ROLE_STORE_ADMIN' => 'Store admin',
		'ROLE_STORE_MANAGER' => 'Store manager',
		'ROLE_USER' => 'User'
	];

	const ROLES_DESCRIPTION = [
		'ROLE_SUPER_ADMIN' => 'Role with all permissions',
		'ROLE_ADMIN' => 'Role admin',
		'ROLE_STORE_ADMIN' => 'Store admin',
		'ROLE_STORE_MANAGER' => 'Store manager',
		'ROLE_USER' => 'Simple user with minimum privileges'
	];

	public function load(ObjectManager $manager): void
	{
		$superAdmin = (new User())
			->setEmail('tolik1794@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O');
		$manager->persist($superAdmin);

		$admin = (new User())
			->setEmail('admin@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O');
		$manager->persist($admin);

		$storeAdmin = (new User())
			->setEmail('storeAdmin@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O');
		$manager->persist($storeAdmin);

		$storeManager = (new User())
			->setEmail('storeManager@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O');
		$manager->persist($storeManager);

		$user = (new User())
			->setEmail('user@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O');
		$manager->persist($user);

		foreach (self::ROLES as $roleKey => $roleValue) {
			$role = (new Role())
				->setKey($roleKey)
				->setValue($roleValue)
				->setDescription(self::ROLES_DESCRIPTION[$roleKey])
			;

			switch ($roleKey) {
				case 'ROLE_SUPER_ADMIN':
					$superAdmin->addRole($role);
					break;
				case 'ROLE_ADMIN':
					$admin->addRole($role)->setParent($superAdmin);
					break;
				case 'ROLE_STORE_ADMIN':
					$storeAdmin->addRole($role)->setParent($admin);
					break;
				case 'ROLE_STORE_MANAGER':
					$storeManager->addRole($role)->setParent($storeAdmin);
					break;
				case 'ROLE_USER':
					$user->addRole($role)->setParent($storeAdmin);
					break;
			}

			$manager->persist($role);
		}

		$manager->flush();
	}
}

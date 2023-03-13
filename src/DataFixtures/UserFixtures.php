<?php

namespace App\DataFixtures;

use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$manager->persist((new User())
			->setEmail('tolik1794@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setNickname('tolik1794')
			->setFirstName('Anatolii')
			->setLastName('Korotkyi')
			->setRoles([RoleEnum::ROLE_SUPER_ADMIN->name])
			->setDateOfBirth(DateTime::createFromFormat('d/m/Y', '02/07/1994')));

		$manager->persist((new User())
			->setEmail('admin@gmail.com')
			->setNickname('admin')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles([RoleEnum::ROLE_ADMIN->name]));

		$manager->persist((new User())
			->setEmail('storeAdmin@gmail.com')
			->setNickname('storeAdmin')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles([RoleEnum::ROLE_STORE_ADMIN->name]));

		$manager->persist((new User())
			->setEmail('storeManager@gmail.com')
			->setNickname('storeManager')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles([RoleEnum::ROLE_STORE_MANAGER->name]));

		$manager->persist((new User())
			->setEmail('user@gmail.com')
			->setNickname('user')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles([RoleEnum::ROLE_USER->name]));

		$manager->flush();
	}
}

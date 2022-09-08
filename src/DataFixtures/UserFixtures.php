<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$user = new User();

		$user
			->setEmail('tolik1794@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles(['ROLE_SUPER_ADMIN']);

		$manager->persist($user);

		$manager->flush();
	}
}

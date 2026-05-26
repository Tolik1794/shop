<?php

namespace App\DataFixtures;

use App\Entity\User\User;
use App\Entity\User\UserGroup;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
	public function getDependencies(): array
	{
		return [
			PermissionFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$manager->persist((new User())
			->setEmail('tolik1794@gmail.com')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setNickname('tolik1794')
			->setFirstName('Anatolii')
			->setLastName('Korotkyi')
			->setRoles(['ROLE_SUPER_ADMIN'])
			->addGroup($this->group($manager, 'super_admin'))
			->setDateOfBirth(DateTime::createFromFormat('d/m/Y', '02/07/1994')));

		$manager->persist((new User())
			->setEmail('admin@gmail.com')
			->setNickname('admin')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles(['ROLE_ADMIN'])
			->addGroup($this->group($manager, 'admin')));

		$manager->persist((new User())
			->setEmail('storeAdmin@gmail.com')
			->setNickname('storeAdmin')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles(['ROLE_STORE_ADMIN'])
			->addGroup($this->group($manager, 'store_admin')));

		$manager->persist((new User())
			->setEmail('storeManager@gmail.com')
			->setNickname('storeManager')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles(['ROLE_STORE_MANAGER'])
			->addGroup($this->group($manager, 'manager')));

		$manager->persist((new User())
			->setEmail('user@gmail.com')
			->setNickname('user')
			->setPassword('$2y$13$J/3zL/fi2lIdHf6W31zCJOZAR7w48ZVoHY/LD8ZcSQN2LsuN38s1O')
			->setRoles(['ROLE_USER'])
			->addGroup($this->group($manager, 'user')));

		$manager->flush();
	}

	private function group(ObjectManager $manager, string $code): UserGroup
	{
		$group = $manager->getRepository(UserGroup::class)->findOneBy(['code' => $code]);

		if (!$group instanceof UserGroup) {
			throw new \RuntimeException(sprintf('User group "%s" must be loaded before users.', $code));
		}

		return $group;
	}
}

<?php

namespace App\DataFixtures;

use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class StoreFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$faker = Factory::create();

		for ($i = 0; $i <= 50; $i++) {
			$store = new Store();
			$store->setDescription($faker->text())
				->setEmail($faker->email())
				->setName($faker->company())
				->setPhone($faker->phoneNumber())
				->setSlug($faker->slug())
				->setStatus(ActiveStatusEnum::INACTIVE);

			$manager->persist($store);
		}

		$manager->flush();
	}
}

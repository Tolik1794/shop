<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Store;
use App\Entity\Supplier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use RuntimeException;

class CustomerSupplierFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
	public static function getGroups(): array
	{
		return ['parties'];
	}

	public function getDependencies(): array
	{
		return [
			StoreFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$stores = $manager->getRepository(Store::class)->findAll();

		if (!$stores) {
			throw new RuntimeException('Load StoreFixtures before CustomerSupplierFixtures.');
		}

		$faker = Factory::create();

		foreach ($stores as $store) {
			for ($i = 1; $i <= 5; $i++) {
				$email = sprintf('customer.%d.store.%d@example.com', $i, $store->getId());

				if (!$manager->getRepository(Customer::class)->findOneBy(['store' => $store, 'email' => $email])) {
					$manager->persist((new Customer())
						->setStore($store)
						->setName($faker->name())
						->setPhone($faker->phoneNumber())
						->setEmail($email)
						->setComment($faker->optional()->sentence()));
				}
			}

			for ($i = 1; $i <= 5; $i++) {
				$email = sprintf('supplier.%d.store.%d@example.com', $i, $store->getId());

				if (!$manager->getRepository(Supplier::class)->findOneBy(['store' => $store, 'email' => $email])) {
					$manager->persist((new Supplier())
						->setStore($store)
						->setName($faker->company())
						->setPhone($faker->phoneNumber())
						->setEmail($email)
						->setComment($faker->optional()->sentence()));
				}
			}
		}

		$manager->flush();
	}
}

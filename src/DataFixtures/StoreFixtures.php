<?php

namespace App\DataFixtures;

use App\Entity\Category;
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
		$manager->persist($myClothingStore = (new Store())
			->setDescription($faker->text())
			->setEmail('my.clothing.store@store.ua')
			->setName('My Clothing Store')
			->setPhone(380666146554)
			->setSlug('my_clothing_store')
			->setStatus(ActiveStatusEnum::ACTIVE));

		$manager->persist($fashion = (new Category())
			->setName('Fashion')
			->setLevel(1)
			->setStore($myClothingStore));

		$manager->persist($fashions[] = (new Category())
			->setName('Male')
			->setLevel(2)
			->setFirstParent($fashion)
			->setParent($fashion)
			->setStore($myClothingStore));

		$manager->persist($fashions[] = (new Category())
			->setName('Female')
			->setLevel(2)
			->setFirstParent($fashion)
			->setParent($fashion)
			->setStore($myClothingStore));

		foreach ($fashions as $fashionCategory) {
			$manager->persist($clothing = (new Category())
				->setName('Clothing')
				->setLevel(3)
				->setFirstParent($fashion)
				->setParent($fashionCategory)
				->setStore($myClothingStore));

			$manager->persist((new Category())
				->setName('Outerwear')
				->setLevel(4)
				->setFirstParent($fashion)
				->setParent($clothing)
				->setStore($myClothingStore));

			$manager->persist((new Category())
				->setName('Sport')
				->setLevel(4)
				->setFirstParent($fashion)
				->setParent($clothing)
				->setStore($myClothingStore));

			$manager->persist((new Category())
				->setName('Shirts and T-shirts')
				->setLevel(4)
				->setFirstParent($fashion)
				->setParent($clothing)
				->setStore($myClothingStore));

			$manager->persist((new Category())
				->setName('Coats, sweaters and cardigans')
				->setLevel(4)
				->setFirstParent($fashion)
				->setParent($clothing)
				->setStore($myClothingStore));

			$manager->persist((new Category())
				->setName('Jeans, pants')
				->setLevel(4)
				->setFirstParent($fashion)
				->setParent($clothing)
				->setStore($myClothingStore));

			$manager->persist((new Category())
				->setName('Suits and jackets')
				->setLevel(4)
				->setFirstParent($fashion)
				->setParent($clothing)
				->setStore($myClothingStore));


			$manager->persist($footgear = (new Category())
				->setName('Footgear')
				->setLevel(3)
				->setFirstParent($fashion)
				->setParent($fashionCategory)
				->setStore($myClothingStore));


			$manager->persist($accessories = (new Category())
				->setName('Bags and accessories')
				->setLevel(3)
				->setFirstParent($fashion)
				->setParent($fashionCategory)
				->setStore($myClothingStore));
		}

		$manager->persist(($myToyStore = new Store())
			->setDescription($faker->text())
			->setEmail('my.toy.store@store.ua')
			->setName('My Toy Store')
			->setPhone(380956554307)
			->setSlug('my_toy_store')
			->setStatus(ActiveStatusEnum::ACTIVE));

		$manager->persist((new Category())
			->setName('Goods for children')
			->setLevel(1)
			->setStore($myToyStore));

		$manager->flush();
	}
}

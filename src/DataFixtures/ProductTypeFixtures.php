<?php

namespace App\DataFixtures;

use App\Entity\ProductType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductTypeFixtures extends Fixture
{
	private const TYPES = [
		'finished_good',
		'material',
		'component',
		'service',
	];

	public function load(ObjectManager $manager): void
	{
		$repository = $manager->getRepository(ProductType::class);

		foreach (self::TYPES as $name) {
			if ($repository->findOneBy(['name' => $name])) {
				continue;
			}

			$manager->persist((new ProductType())->setName($name));
		}

		$manager->flush();
	}
}

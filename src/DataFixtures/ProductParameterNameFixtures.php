<?php

namespace App\DataFixtures;

use App\Entity\ProductParameterName;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductParameterNameFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist(
			(new ProductParameterName())
				->setName('weight')
	            ->setDescription('Weight of product')
        );

        $manager->persist(
			(new ProductParameterName())
				->setName('volume')
	            ->setDescription('Volume of product in delivery box')
        );

        $manager->persist(
			(new ProductParameterName())
				->setName('width')
	            ->setDescription('Width of product in delivery box')
        );

        $manager->persist(
			(new ProductParameterName())
				->setName('depth')
	            ->setDescription('Depth of product in delivery box')
        );

        $manager->persist(
			(new ProductParameterName())
				->setName('material')
	            ->setDescription('Material of product')
        );

        $manager->flush();
    }
}

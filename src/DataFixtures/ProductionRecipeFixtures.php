<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ProductionRecipeFixtures extends Fixture implements FixtureGroupInterface
{
	public function __construct(private readonly ProductionRecipeFixtureSeeder $seeder)
	{
	}

	public static function getGroups(): array
	{
		return ['production_recipes'];
	}

	public function load(ObjectManager $manager): void
	{
		$this->seeder->seed($manager);
	}
}

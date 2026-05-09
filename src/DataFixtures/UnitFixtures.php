<?php

namespace App\DataFixtures;

use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UnitFixtures extends Fixture implements DependentFixtureInterface
{
	public function getDependencies(): array
	{
		return [
			StoreFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$units = [
			'pcs' => ['name' => 'Pieces', 'precision' => 0],
			'kg' => ['name' => 'Kilogram', 'precision' => 3],
			'm' => ['name' => 'Meter', 'precision' => 3],
		];

		$repository = $manager->getRepository(Unit::class);

		foreach ($manager->getRepository(Store::class)->findAll() as $store) {
			foreach ($units as $code => $data) {
				if ($repository->findOneBy(['store' => $store, 'code' => $code])) {
					continue;
				}

				$manager->persist((new Unit())
					->setStore($store)
					->setCode($code)
					->setName($data['name'])
					->setPrecision($data['precision'])
					->setStatus(ActiveStatusEnum::ACTIVE));
			}
		}

		$manager->flush();
	}
}

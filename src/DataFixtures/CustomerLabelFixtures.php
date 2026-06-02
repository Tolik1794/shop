<?php

namespace App\DataFixtures;

use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerLabelFixtures extends Fixture implements DependentFixtureInterface
{
	public function getDependencies(): array
	{
		return [
			StoreFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$labels = [
			'aggressive' => ['name' => 'Aggressive', 'color' => 'bg-danger', 'sortOrder' => 10],
			'compliant' => ['name' => 'Compliant', 'color' => 'bg-success', 'sortOrder' => 20],
			'rational' => ['name' => 'Rational', 'color' => 'bg-info text-dark', 'sortOrder' => 30],
			'vip' => ['name' => 'VIP', 'color' => 'bg-warning text-dark', 'sortOrder' => 40],
			'problematic' => ['name' => 'Problematic', 'color' => 'bg-danger', 'sortOrder' => 50],
			'regular' => ['name' => 'Regular', 'color' => 'bg-primary', 'sortOrder' => 60],
			'bargainer' => ['name' => 'Bargainer', 'color' => 'bg-secondary', 'sortOrder' => 70],
			'needs_attention' => ['name' => 'Needs attention', 'color' => 'bg-warning text-dark', 'sortOrder' => 80],
			'frequent_returns' => ['name' => 'Frequently returns goods', 'color' => 'bg-dark', 'sortOrder' => 90],
		];

		$repository = $manager->getRepository(CustomerLabel::class);

		foreach ($manager->getRepository(Store::class)->findAll() as $store) {
			foreach ($labels as $code => $data) {
				if ($repository->findOneBy(['store' => $store, 'code' => $code, 'deletedAt' => null])) {
					continue;
				}

				$manager->persist((new CustomerLabel())
					->setStore($store)
					->setCode($code)
					->setName($data['name'])
					->setColor($data['color'])
					->setSortOrder($data['sortOrder'])
					->setStatus(ActiveStatusEnum::ACTIVE));
			}
		}

		$manager->flush();
	}
}

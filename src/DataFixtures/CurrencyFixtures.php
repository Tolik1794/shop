<?php

namespace App\DataFixtures;

use App\Entity\Currency;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CurrencyFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$currencies = [
			'UAH' => ['name' => 'Ukrainian hryvnia', 'symbol' => 'UAH', 'decimalPlaces' => 2],
			'USD' => ['name' => 'US dollar', 'symbol' => '$', 'decimalPlaces' => 2],
			'EUR' => ['name' => 'Euro', 'symbol' => 'EUR', 'decimalPlaces' => 2],
		];

		$repository = $manager->getRepository(Currency::class);

		foreach ($currencies as $code => $data) {
			if ($repository->find($code)) {
				continue;
			}

			$manager->persist((new Currency())
				->setCode($code)
				->setName($data['name'])
				->setSymbol($data['symbol'])
				->setDecimalPlaces($data['decimalPlaces'])
				->setStatus(ActiveStatusEnum::ACTIVE));
		}

		$manager->flush();
	}
}

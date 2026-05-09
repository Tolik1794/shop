<?php

namespace App\DataFixtures;

use App\Entity\Currency;
use App\Entity\ExchangeRate;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

class ExchangeRateFixtures extends Fixture implements DependentFixtureInterface
{
	public function getDependencies(): array
	{
		return [
			CurrencyFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$repository = $manager->getRepository(ExchangeRate::class);
		$currencyRepository = $manager->getRepository(Currency::class);
		$validFrom = new DateTimeImmutable('2026-01-01 00:00:00');

		$rates = [
			['from' => 'USD', 'to' => 'UAH', 'rate' => '41.00000000'],
			['from' => 'EUR', 'to' => 'UAH', 'rate' => '44.00000000'],
			['from' => 'UAH', 'to' => 'USD', 'rate' => '0.02439024'],
			['from' => 'UAH', 'to' => 'EUR', 'rate' => '0.02272727'],
		];

		foreach ($rates as $rate) {
			$fromCurrency = $currencyRepository->find($rate['from']);
			$toCurrency = $currencyRepository->find($rate['to']);

			if (!$fromCurrency instanceof Currency || !$toCurrency instanceof Currency) {
				throw new RuntimeException('Load CurrencyFixtures before ExchangeRateFixtures.');
			}

			if ($repository->findOneBy([
				'fromCurrency' => $fromCurrency,
				'toCurrency' => $toCurrency,
				'store' => null,
				'validFrom' => $validFrom,
			])) {
				continue;
			}

			$manager->persist((new ExchangeRate())
				->setFromCurrency($fromCurrency)
				->setToCurrency($toCurrency)
				->setRate($rate['rate'])
				->setValidFrom($validFrom)
				->setSource('fixtures'));
		}

		$manager->flush();
	}
}

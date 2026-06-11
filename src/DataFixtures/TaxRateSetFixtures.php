<?php

namespace App\DataFixtures;

use App\Entity\TaxRateSet;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TaxRateSetFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$repository = $manager->getRepository(TaxRateSet::class);

		if ($repository->findOneBy(['year' => 2026])) {
			return;
		}

		// Values as of 2026-01-01. Verify before relying: minimum wage, limits
		// and rates must be confirmed against current legislation by the user.
		$manager->persist((new TaxRateSet())
			->setYear(2026)
			->setMinimumWage('8647.0000')
			->setSubsistenceMinimum('3328.0000')
			->setGroup1IncomeLimit('1444049.0000')
			->setGroup2IncomeLimit('7211598.0000')
			->setGroup3IncomeLimit('10091049.0000')
			->setGroup1EpMonthly('332.8000')
			->setGroup2EpMonthly('1729.4000')
			->setGroup3EpRatePct('5.00')
			->setGroup3EpRateVatPct('3.00')
			->setEsvRatePct('22.00')
			->setEsvMonthlyMin('1902.3400')
			->setVzGroup12Monthly('864.7000')
			->setVzGroup3RatePct('1.00')
			->setComment('Verify before relying: значення станом на 01.01.2026, перевірити актуальність перед використанням у розрахунках.'));

		$manager->flush();
	}
}

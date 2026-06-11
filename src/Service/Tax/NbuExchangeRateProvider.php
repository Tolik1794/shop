<?php

namespace App\Service\Tax;

use App\Entity\Currency;
use App\Entity\NbuExchangeRate;
use App\Repository\NbuExchangeRateRepository;
use DateTimeImmutable;

class NbuExchangeRateProvider
{
	public function __construct(private readonly NbuExchangeRateRepository $nbuExchangeRateRepository)
	{
	}

	/**
	 * Returns the NBU rate to UAH for the given date, or null when no rate is stored.
	 */
	public function getRate(Currency $currency, DateTimeImmutable $date): ?string
	{
		if ($currency->getCode() === 'UAH') {
			return '1.00000000';
		}

		$rate = $this->nbuExchangeRateRepository->findOneBy([
			'currency' => $currency,
			'date' => $date->setTime(0, 0),
		]);

		return $rate instanceof NbuExchangeRate ? $rate->getRate() : null;
	}
}

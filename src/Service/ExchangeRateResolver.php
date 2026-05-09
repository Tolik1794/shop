<?php

namespace App\Service;

use App\Entity\Currency;
use App\Entity\Store;
use App\Repository\ExchangeRateRepository;
use DateTimeImmutable;
use RuntimeException;

class ExchangeRateResolver
{
	public function __construct(private readonly ExchangeRateRepository $exchangeRateRepository)
	{
	}

	public function resolve(
		Currency $fromCurrency,
		Currency $toCurrency,
		?Store $store = null,
		?DateTimeImmutable $date = null,
	): string {
		if ($fromCurrency->getCode() === $toCurrency->getCode()) {
			return '1.00000000';
		}

		$date ??= new DateTimeImmutable();
		$exchangeRate = $this->exchangeRateRepository->findRateForDate($fromCurrency, $toCurrency, $store, $date);

		if (!$exchangeRate) {
			throw new RuntimeException(sprintf(
				'Exchange rate %s -> %s was not found.',
				$fromCurrency->getCode(),
				$toCurrency->getCode()
			));
		}

		return (string)$exchangeRate->getRate();
	}
}

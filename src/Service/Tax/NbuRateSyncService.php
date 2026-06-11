<?php

namespace App\Service\Tax;

use App\Entity\Currency;
use App\Entity\NbuExchangeRate;
use App\Repository\NbuExchangeRateRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pulls official NBU exchange rates (to UAH) for all non-UAH currencies
 * configured in the project and stores them in nbu_exchange_rate.
 * Upserts are idempotent: re-running the sync for the same date updates rates in place.
 */
class NbuRateSyncService
{
	private const string API_URL = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange';

	public function __construct(
		private readonly HttpClientInterface $httpClient,
		private readonly EntityManagerInterface $entityManager,
		private readonly NbuExchangeRateRepository $nbuExchangeRateRepository,
	)
	{
	}

	/**
	 * @return array{date: string, saved: list<array{currency: string, rate: string}>, missing: list<string>}
	 */
	public function sync(DateTimeImmutable $date): array
	{
		$date = $date->setTime(0, 0);
		$apiRates = $this->fetchRates($date);

		$saved = [];
		$missing = [];

		foreach ($this->targetCurrencies() as $currency) {
			$code = $currency->getCode();
			if (!isset($apiRates[$code])) {
				$missing[] = $code;
				continue;
			}

			$rate = $this->nbuExchangeRateRepository->findOneBy([
				'currency' => $currency,
				'date' => $date,
			]) ?? (new NbuExchangeRate())
				->setCurrency($currency)
				->setDate($date);

			$rate
				->setRate($apiRates[$code])
				->setSource('nbu_api');

			$this->entityManager->persist($rate);
			$saved[] = ['currency' => $code, 'rate' => $apiRates[$code]];
		}

		$this->entityManager->flush();

		return [
			'date' => $date->format('Y-m-d'),
			'saved' => $saved,
			'missing' => $missing,
		];
	}

	/**
	 * @return array<string, string> currency code => rate to UAH (decimal string, 8 digits scale)
	 */
	private function fetchRates(DateTimeImmutable $date): array
	{
		$response = $this->httpClient->request('GET', self::API_URL, [
			'query' => [
				'date' => $date->format('Ymd'),
				'json' => '',
			],
		]);

		$payload = $response->toArray();
		$rates = [];

		foreach ($payload as $row) {
			if (!is_array($row) || !isset($row['cc'], $row['rate'])) {
				continue;
			}

			$rates[(string) $row['cc']] = number_format((float) $row['rate'], 8, '.', '');
		}

		if ($rates === []) {
			throw new RuntimeException(sprintf('NBU API returned no rates for %s.', $date->format('Y-m-d')));
		}

		return $rates;
	}

	/**
	 * @return Currency[]
	 */
	private function targetCurrencies(): array
	{
		return array_values(array_filter(
			$this->entityManager->getRepository(Currency::class)->findAll(),
			static fn (Currency $currency): bool => $currency->getCode() !== 'UAH'
		));
	}
}

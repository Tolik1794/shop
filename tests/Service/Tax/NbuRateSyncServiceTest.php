<?php

namespace App\Tests\Service\Tax;

use App\Entity\Currency;
use App\Entity\NbuExchangeRate;
use App\Repository\NbuExchangeRateRepository;
use App\Service\Tax\NbuRateSyncService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NbuRateSyncServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testSyncStoresRatesForConfiguredCurrenciesAndIsIdempotent(): void
	{
		$this->persistCurrency('UAH');
		$this->persistCurrency('USD');
		$date = new DateTimeImmutable('2026-04-15');

		$nbuResponse = json_encode([
			['r030' => 840, 'txt' => 'Долар США', 'rate' => 41.5555, 'cc' => 'USD', 'exchangedate' => '15.04.2026'],
			['r030' => 978, 'txt' => 'Євро', 'rate' => 45.1234, 'cc' => 'EUR', 'exchangedate' => '15.04.2026'],
		], JSON_THROW_ON_ERROR);

		$service = $this->createService(new MockHttpClient([
			new MockResponse($nbuResponse, ['response_headers' => ['content-type' => 'application/json']]),
			new MockResponse($nbuResponse, ['response_headers' => ['content-type' => 'application/json']]),
		]));

		$report = $service->sync($date);

		$savedCodes = array_column($report['saved'], 'currency');
		self::assertContains('USD', $savedCodes);
		self::assertNotContains('UAH', $savedCodes);

		$usd = $this->entityManager->getRepository(Currency::class)->find('USD');
		$stored = $this->entityManager->getRepository(NbuExchangeRate::class)->findOneBy([
			'currency' => $usd,
			'date' => $date->setTime(0, 0),
		]);

		self::assertInstanceOf(NbuExchangeRate::class, $stored);
		self::assertSame('41.55550000', $stored->getRate());
		self::assertSame('nbu_api', $stored->getSource());

		// Second sync for the same date updates in place, no duplicates.
		$service->sync($date);

		$rows = $this->entityManager->getRepository(NbuExchangeRate::class)->findBy([
			'currency' => $usd,
			'date' => $date->setTime(0, 0),
		]);
		self::assertCount(1, $rows);
	}

	private function createService(MockHttpClient $httpClient): NbuRateSyncService
	{
		return new NbuRateSyncService(
			$httpClient,
			$this->entityManager,
			static::getContainer()->get(NbuExchangeRateRepository::class),
		);
	}

	private function persistCurrency(string $code): void
	{
		if ($this->entityManager->getRepository(Currency::class)->find($code)) {
			return;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($code)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();
	}
}

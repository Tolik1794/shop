<?php

namespace App\Tests\Service;

use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Store;
use App\Manager\ExchangeRateManager;
use App\Repository\ExchangeRateRepository;
use App\Service\ExchangeRateResolver;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExchangeRateResolverTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private ExchangeRateResolver $exchangeRateResolver;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->exchangeRateResolver = new ExchangeRateResolver(
			static::getContainer()->get(ExchangeRateRepository::class)
		);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->exchangeRateResolver);
	}

	public function testSameCurrencyReturnsOne(): void
	{
		$currency = $this->persistCurrency('T' . substr(uniqid(), -2), 'Test currency');

		self::assertSame('1.00000000', $this->exchangeRateResolver->resolve($currency, $currency));
	}

	public function testGlobalRateIsUsedWhenStoreRateDoesNotExist(): void
	{
		$fromCurrency = $this->persistCurrency('A' . substr(uniqid(), -2), 'From currency');
		$toCurrency = $this->persistCurrency('B' . substr(uniqid(), -2), 'To currency');
		$store = $this->persistStore('exchange-global-' . uniqid(), $toCurrency);
		$date = new DateTimeImmutable('2026-02-01 00:00:00');

		$this->persistExchangeRate($fromCurrency, $toCurrency, null, '12.50000000', new DateTimeImmutable('2026-01-01 00:00:00'));

		self::assertSame('12.50000000', $this->exchangeRateResolver->resolve($fromCurrency, $toCurrency, $store, $date));
	}

	public function testStoreRateHasPriorityOverGlobalRate(): void
	{
		$fromCurrency = $this->persistCurrency('C' . substr(uniqid(), -2), 'From currency');
		$toCurrency = $this->persistCurrency('D' . substr(uniqid(), -2), 'To currency');
		$store = $this->persistStore('exchange-store-' . uniqid(), $toCurrency);
		$date = new DateTimeImmutable('2026-02-01 00:00:00');

		$this->persistExchangeRate($fromCurrency, $toCurrency, null, '12.50000000', new DateTimeImmutable('2026-01-01 00:00:00'));
		$this->persistExchangeRate($fromCurrency, $toCurrency, $store, '13.50000000', new DateTimeImmutable('2026-01-01 00:00:00'));

		self::assertSame('13.50000000', $this->exchangeRateResolver->resolve($fromCurrency, $toCurrency, $store, $date));
	}

	public function testNewRateClosesPreviousOpenRate(): void
	{
		$fromCurrency = $this->persistCurrency('E' . substr(uniqid(), -2), 'From currency');
		$toCurrency = $this->persistCurrency('F' . substr(uniqid(), -2), 'To currency');
		$store = $this->persistStore('exchange-close-' . uniqid(), $toCurrency);
		$validFrom = new DateTimeImmutable('2026-02-01 00:00:00');
		$previousExchangeRate = $this->persistExchangeRate($fromCurrency, $toCurrency, $store, '12.50000000', new DateTimeImmutable('2026-01-01 00:00:00'));
		$exchangeRateManager = new ExchangeRateManager($this->entityManager);

		$exchangeRateManager->saveWithTimeline((new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate('13.50000000')
			->setValidFrom($validFrom)
		);

		$this->entityManager->refresh($previousExchangeRate);

		self::assertEquals($validFrom, $previousExchangeRate->getValidTo());
		self::assertSame('13.50000000', $this->exchangeRateResolver->resolve($fromCurrency, $toCurrency, $store, $validFrom));
	}

	public function testNewRateInsideClosedIntervalIsBlocked(): void
	{
		$fromCurrency = $this->persistCurrency('G' . substr(uniqid(), -2), 'From currency');
		$toCurrency = $this->persistCurrency('H' . substr(uniqid(), -2), 'To currency');
		$store = $this->persistStore('exchange-block-' . uniqid(), $toCurrency);
		$repository = static::getContainer()->get(ExchangeRateRepository::class);

		$this->persistExchangeRate(
			$fromCurrency,
			$toCurrency,
			$store,
			'12.50000000',
			new DateTimeImmutable('2026-01-01 00:00:00'),
			new DateTimeImmutable('2026-02-01 00:00:00')
		);

		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate('13.50000000')
			->setValidFrom(new DateTimeImmutable('2026-01-15 00:00:00'));

		self::assertTrue($repository->hasBlockingRateForNewStart($exchangeRate));
	}

	private function persistCurrency(string $code, string $name): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($name)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}

	private function persistStore(string $slug, Currency $baseCurrency): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($baseCurrency);

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}

	private function persistExchangeRate(
		Currency $fromCurrency,
		Currency $toCurrency,
		?Store $store,
		string $rate,
		DateTimeImmutable $validFrom,
		?DateTimeImmutable $validTo = null,
	): ExchangeRate {
		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate($rate)
			->setValidFrom($validFrom)
			->setValidTo($validTo);

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();

		return $exchangeRate;
	}
}

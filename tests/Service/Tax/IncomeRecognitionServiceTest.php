<?php

namespace App\Tests\Service\Tax;

use App\Dto\Tax\RecognitionOutcome;
use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\IncomeRecord;
use App\Entity\IncomeRecordHistory;
use App\Entity\LegalEntity;
use App\Entity\NbuExchangeRate;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\Store;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\PaymentDirectionEnum;
use App\Enum\TaxSystemEnum;
use App\Manager\PaymentManager;
use App\Service\Tax\IncomeRecognitionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IncomeRecognitionServiceTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private PaymentManager $paymentManager;
	private IncomeRecognitionService $incomeRecognitionService;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->paymentManager = static::getContainer()->get(PaymentManager::class);
		$this->incomeRecognitionService = static::getContainer()->get(IncomeRecognitionService::class);
	}

	public function testIncomingUahPaymentCreatesIncomeRecord(): void
	{
		$uah = $this->persistUah();
		$legalEntity = $this->persistLegalEntity();
		$store = $this->persistStore('income-uah-' . uniqid(), $uah, $legalEntity);
		$order = $this->persistOrder($store, $uah, '100.0000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($uah)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('100.0000')
			->setPaidAt(new DateTimeImmutable('2026-03-10 12:00:00'));

		$this->paymentManager->savePayment($payment);

		$record = $this->findRecordForPayment($payment);

		self::assertInstanceOf(IncomeRecord::class, $record);
		self::assertSame(IncomeClassificationEnum::INCOME, $record->getClassification());
		self::assertSame('100.0000', $record->getAmountUah());
		self::assertSame('1.00000000', $record->getNbuExchangeRate());
		self::assertSame($legalEntity->getId(), $record->getLegalEntity()?->getId());
		self::assertSame('2026-03-10', $record->getRecognizedAt()?->format('Y-m-d'));

		$history = $this->entityManager->getRepository(IncomeRecordHistory::class)
			->findOneBy(['incomeRecord' => $record]);
		self::assertInstanceOf(IncomeRecordHistory::class, $history);
		self::assertSame('income.recognized', $history->getEventKey());
	}

	public function testReversalCreatesRefundRecordInReversalPeriod(): void
	{
		$uah = $this->persistUah();
		$legalEntity = $this->persistLegalEntity();
		$store = $this->persistStore('income-refund-' . uniqid(), $uah, $legalEntity);
		$order = $this->persistOrder($store, $uah, '100.0000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($uah)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('100.0000')
			->setPaidAt(new DateTimeImmutable('2026-01-15 09:00:00'));
		$this->paymentManager->savePayment($payment);

		$reversal = $this->paymentManager->reversePayment($payment, 'Customer returned the goods');

		$incomeRecord = $this->findRecordForPayment($payment);
		$refundRecord = $this->findRecordForPayment($reversal);

		self::assertInstanceOf(IncomeRecord::class, $refundRecord);
		self::assertSame(IncomeClassificationEnum::REFUND, $refundRecord->getClassification());
		self::assertSame($incomeRecord?->getId(), $refundRecord->getRefundOfIncomeRecord()?->getId());
		// Refund is recognized in the reversal period, not in the original payment period.
		self::assertSame(
			$reversal->getPaidAt()->format('Y-m-d'),
			$refundRecord->getRecognizedAt()?->format('Y-m-d')
		);
	}

	public function testPaymentForStoreWithoutLegalEntityIsSavedWithoutIncomeRecord(): void
	{
		$uah = $this->persistUah();
		$store = $this->persistStore('income-no-entity-' . uniqid(), $uah, null);
		$order = $this->persistOrder($store, $uah, '50.0000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($uah)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('50.0000')
			->setPaidAt(new DateTimeImmutable());

		$this->paymentManager->savePayment($payment);

		self::assertNotNull($payment->getId());
		self::assertNull($this->findRecordForPayment($payment));
	}

	public function testRepeatedRecognitionIsIdempotent(): void
	{
		$uah = $this->persistUah();
		$legalEntity = $this->persistLegalEntity();
		$store = $this->persistStore('income-idempotent-' . uniqid(), $uah, $legalEntity);
		$order = $this->persistOrder($store, $uah, '70.0000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($uah)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('70.0000')
			->setPaidAt(new DateTimeImmutable());
		$this->paymentManager->savePayment($payment);

		$outcome = $this->incomeRecognitionService->recognizePayment($payment);

		self::assertSame(RecognitionOutcome::STATUS_SKIPPED, $outcome->status);
		self::assertSame(RecognitionOutcome::REASON_ALREADY_RECOGNIZED, $outcome->reason);
		self::assertCount(1, $this->entityManager->getRepository(IncomeRecord::class)->findBy([
			'sourceType' => IncomeSourceTypeEnum::PAYMENT,
			'sourceId' => $payment->getId(),
		]));
	}

	public function testForeignCurrencyWithoutNbuRateIsSkippedButPaymentSaved(): void
	{
		$uah = $this->persistUah();
		$usd = $this->persistCurrency('USD', 'US dollar');
		$legalEntity = $this->persistLegalEntity();
		$store = $this->persistStore('income-fx-norate-' . uniqid(), $uah, $legalEntity);
		$order = $this->persistOrder($store, $usd, '100.0000', '4100.0000');
		$this->persistCommercialRate($usd, $uah, $store, '41.00000000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($usd)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('100.0000')
			->setPaidAt(new DateTimeImmutable('2026-04-01 10:00:00'));

		$this->paymentManager->savePayment($payment);

		self::assertNotNull($payment->getId());
		self::assertNull($this->findRecordForPayment($payment));
	}

	public function testForeignCurrencyUsesNbuRateForAmountUah(): void
	{
		$uah = $this->persistUah();
		$usd = $this->persistCurrency('USD', 'US dollar');
		$legalEntity = $this->persistLegalEntity();
		$store = $this->persistStore('income-fx-rate-' . uniqid(), $uah, $legalEntity);
		$order = $this->persistOrder($store, $usd, '100.0000', '4100.0000');
		$this->persistCommercialRate($usd, $uah, $store, '41.00000000');
		$this->persistNbuRate($usd, new DateTimeImmutable('2026-04-02'), '41.55550000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($usd)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('100.0000')
			->setPaidAt(new DateTimeImmutable('2026-04-02 10:00:00'));

		$this->paymentManager->savePayment($payment);

		$record = $this->findRecordForPayment($payment);

		self::assertInstanceOf(IncomeRecord::class, $record);
		self::assertSame('41.55550000', $record->getNbuExchangeRate());
		self::assertSame('4155.5500', $record->getAmountUah());
	}

	private function findRecordForPayment(Payment $payment): ?IncomeRecord
	{
		return $this->entityManager->getRepository(IncomeRecord::class)->findOneBy([
			'sourceType' => IncomeSourceTypeEnum::PAYMENT,
			'sourceId' => $payment->getId(),
		]);
	}

	private function persistUah(): Currency
	{
		return $this->persistCurrency('UAH', 'Ukrainian hryvnia');
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

	private function persistLegalEntity(): LegalEntity
	{
		$legalEntity = (new LegalEntity())
			->setName('ФОП Тестовий ' . uniqid())
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber(substr((string) random_int(1000000000, 9999999999), 0, 10))
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup(3)
			->setEpRate('5.00');

		$this->entityManager->persist($legalEntity);
		$this->entityManager->flush();

		return $legalEntity;
	}

	private function persistStore(string $slug, Currency $baseCurrency, ?LegalEntity $legalEntity): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($baseCurrency)
			->setLegalEntity($legalEntity);

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}

	private function persistOrder(Store $store, Currency $currency, string $totalAmount, ?string $totalAmountBase = null): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('SO-' . uniqid())
			->setTotalAmount($totalAmount)
			->setTotalAmountBase($totalAmountBase ?? $totalAmount)
			->setStatus(OrderStatus::CONFIRMED);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		return $order;
	}

	private function persistCommercialRate(Currency $fromCurrency, Currency $toCurrency, Store $store, string $rate): void
	{
		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate($rate)
			->setValidFrom(new DateTimeImmutable('2020-01-01'));

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();
	}

	private function persistNbuRate(Currency $currency, DateTimeImmutable $date, string $rate): void
	{
		$nbuRate = $this->entityManager->getRepository(NbuExchangeRate::class)->findOneBy([
			'currency' => $currency,
			'date' => $date->setTime(0, 0),
		]) ?? (new NbuExchangeRate())
			->setCurrency($currency)
			->setDate($date->setTime(0, 0));

		$nbuRate
			->setRate($rate)
			->setSource('manual');

		$this->entityManager->persist($nbuRate);
		$this->entityManager->flush();
	}
}

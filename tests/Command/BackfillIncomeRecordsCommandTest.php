<?php

namespace App\Tests\Command;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\Store;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use App\Enum\TaxSystemEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class BackfillIncomeRecordsCommandTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testDryRunReportsWithoutWritingAndApplyIsIdempotent(): void
	{
		$uah = $this->persistCurrency('UAH');
		$legalEntity = $this->persistLegalEntity();
		$store = $this->persistStore('backfill-store-' . uniqid(), $uah, $legalEntity);
		$order = $this->persistOrder($store, $uah, '300.0000');
		// Historical payment persisted directly, bypassing PaymentManager recognition.
		$payment = $this->persistRawPayment($store, $order, $uah, '300.0000', new DateTimeImmutable('2025-11-20 10:00:00'));

		$tester = $this->commandTester();

		$tester->execute(['--store-id' => (string) $store->getId()]);
		$tester->assertCommandIsSuccessful();
		self::assertStringContainsString('dry run', strtolower($tester->getDisplay()));
		self::assertNull($this->findRecordForPayment($payment));

		$tester->execute(['--store-id' => (string) $store->getId(), '--apply' => true]);
		$tester->assertCommandIsSuccessful();

		$record = $this->findRecordForPayment($payment);
		self::assertInstanceOf(IncomeRecord::class, $record);
		self::assertSame('300.0000', $record->getAmountUah());
		self::assertStringContainsString((string) $legalEntity, $tester->getDisplay());

		// Re-applying creates no duplicates.
		$tester->execute(['--store-id' => (string) $store->getId(), '--apply' => true]);
		$tester->assertCommandIsSuccessful();
		self::assertStringContainsString('already_recognized', $tester->getDisplay());
		self::assertCount(1, $this->entityManager->getRepository(IncomeRecord::class)->findBy([
			'sourceType' => IncomeSourceTypeEnum::PAYMENT,
			'sourceId' => $payment->getId(),
		]));
	}

	private function commandTester(): CommandTester
	{
		$application = new Application(self::$kernel);

		return new CommandTester($application->find('app:tax:income-backfill'));
	}

	private function findRecordForPayment(Payment $payment): ?IncomeRecord
	{
		return $this->entityManager->getRepository(IncomeRecord::class)->findOneBy([
			'sourceType' => IncomeSourceTypeEnum::PAYMENT,
			'sourceId' => $payment->getId(),
		]);
	}

	private function persistCurrency(string $code): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($code)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}

	private function persistLegalEntity(): LegalEntity
	{
		$legalEntity = (new LegalEntity())
			->setName('ФОП Backfill ' . uniqid())
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber(substr((string) random_int(1000000000, 9999999999), 0, 10))
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup(3)
			->setEpRate('5.00');

		$this->entityManager->persist($legalEntity);
		$this->entityManager->flush();

		return $legalEntity;
	}

	private function persistStore(string $slug, Currency $baseCurrency, LegalEntity $legalEntity): Store
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

	private function persistOrder(Store $store, Currency $currency, string $totalAmountBase): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('SO-' . uniqid())
			->setTotalAmount($totalAmountBase)
			->setTotalAmountBase($totalAmountBase)
			->setStatus(OrderStatus::CONFIRMED);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		return $order;
	}

	private function persistRawPayment(Store $store, Order $order, Currency $currency, string $amount, DateTimeImmutable $paidAt): Payment
	{
		$payment = (new Payment())
			->setStore($store)
			->setOrder($order)
			->setCurrency($currency)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::BANK_TRANSFER)
			->setAmount($amount)
			->setAmountBase($amount)
			->setExchangeRateToBase('1.00000000')
			->setPaidAt($paidAt);

		$this->entityManager->persist($payment);
		$this->entityManager->flush();

		return $payment;
	}
}

<?php

namespace App\Tests\Manager;

use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\PaymentHistory;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTypeEnum;
use App\Manager\PaymentManager;
use App\Service\History\DocumentTimelineBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PaymentManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private PaymentManager $paymentManager;
	private DocumentTimelineBuilder $documentTimelineBuilder;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->paymentManager = static::getContainer()->get(PaymentManager::class);
		$this->documentTimelineBuilder = static::getContainer()->get(DocumentTimelineBuilder::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->paymentManager, $this->documentTimelineBuilder);
	}

	public function testSavePaymentStoresExchangeRateSnapshotAndRecalculatesOrder(): void
	{
		$baseCurrency = $this->persistCurrency('B' . substr(uniqid(), -2), 'Base currency');
		$paymentCurrency = $this->persistCurrency('P' . substr(uniqid(), -2), 'Payment currency');
		$store = $this->persistStore('payment-' . uniqid(), $baseCurrency);
		$order = $this->persistOrder($store, $baseCurrency, '100.0000')
			->setStatus(OrderStatus::CONFIRMED);
		$this->persistExchangeRate($paymentCurrency, $baseCurrency, $store, '2.00000000');
		$this->entityManager->flush();

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($paymentCurrency)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('50.0000')
			->setPaidAt(new DateTimeImmutable('2026-05-18 10:00:00'));

		$this->paymentManager->savePayment($payment);

		self::assertSame('2.00000000', $payment->getExchangeRateToBase());
		self::assertSame('100.0000', $payment->getAmountBase());
		self::assertSame('100.0000', $order->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::PAID, $order->getPaymentStatus());
		self::assertSame('2026-05-18 10:00:00', $order->getPaidAt()?->format('Y-m-d H:i:s'));

		$history = $this->entityManager->getRepository(PaymentHistory::class)->findOneBy([
			'payment' => $payment,
			'order' => $order,
			'eventKey' => 'payment.recorded',
		]);
		self::assertInstanceOf(PaymentHistory::class, $history);
		self::assertSame('order', $history->getDocumentType());
		self::assertSame($order->getId(), $history->getDocumentId());
		self::assertSame('50.0000', $history->getAmount());
		self::assertSame($paymentCurrency->getCode(), $history->getCurrencyCode());
		self::assertContains('payment.recorded', array_map(
			static fn ($entry): string => $entry->getEventKey(),
			$this->documentTimelineBuilder->forOrder($order),
		));
	}

	public function testSavePaymentRecordsPurchasePaymentHistory(): void
	{
		$currency = $this->persistCurrency('H' . substr(uniqid(), -2), 'Purchase history currency');
		$store = $this->persistStore('payment-purchase-history-' . uniqid(), $currency);
		$purchase = $this->persistPurchase($store, $currency, '80.0000');

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($currency)
			->setPurchase($purchase)
			->setDirection(PaymentDirectionEnum::OUTGOING)
			->setAmount('40.0000')
			->setPaidAt(new DateTimeImmutable('2026-05-18 11:00:00'));

		$this->paymentManager->savePayment($payment);

		$history = $this->entityManager->getRepository(PaymentHistory::class)->findOneBy([
			'payment' => $payment,
			'purchase' => $purchase,
			'eventKey' => 'payment.recorded',
		]);
		self::assertInstanceOf(PaymentHistory::class, $history);
		self::assertSame('purchase', $history->getDocumentType());
		self::assertSame($purchase->getId(), $history->getDocumentId());
		self::assertSame('40.0000', $history->getAmount());
		self::assertContains('payment.recorded', array_map(
			static fn ($entry): string => $entry->getEventKey(),
			$this->documentTimelineBuilder->forPurchase($store, $purchase),
		));
	}

	public function testPaymentMustTargetExactlyOneDocument(): void
	{
		$currency = $this->persistCurrency('X' . substr(uniqid(), -2), 'No target currency');
		$store = $this->persistStore('payment-target-' . uniqid(), $currency);
		$payment = $this->paymentManager->createForStore($store)
			->setAmount('10.0000');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Select exactly one payment document.');

		$this->paymentManager->savePayment($payment);
	}

	public function testPrefillFromOrderUsesDocumentCurrencyAndRemainingAmount(): void
	{
		$baseCurrency = $this->persistCurrency('A' . substr(uniqid(), -2), 'Prefill base currency');
		$documentCurrency = $this->persistCurrency('D' . substr(uniqid(), -2), 'Prefill document currency');
		$store = $this->persistStore('payment-prefill-order-' . uniqid(), $baseCurrency);
		$order = $this->persistOrder($store, $documentCurrency, '300.0000')
			->setExchangeRateToBase('2.00000000')
			->setPaidAmountBase('100.0000');
		$payment = $this->paymentManager->createForStore($store);

		$this->paymentManager->prefillFromOrder($payment, $order);

		self::assertSame($order, $payment->getOrder());
		self::assertSame($documentCurrency, $payment->getCurrency());
		self::assertSame(PaymentDirectionEnum::INCOMING, $payment->getDirection());
		self::assertSame('100.0000', $payment->getAmount());
		self::assertSame([
			'direction' => 'incoming',
			'currency' => $documentCurrency->getCode(),
			'amount' => '100.0000',
		], $this->paymentManager->defaultsForOrder($order));
	}

	public function testPrefillRefundFromOrderUsesPaidAmountAndKeepsPaymentTypeEditable(): void
	{
		$baseCurrency = $this->persistCurrency('F' . substr(uniqid(), -2), 'Refund prefill base currency');
		$documentCurrency = $this->persistCurrency('G' . substr(uniqid(), -2), 'Refund prefill document currency');
		$store = $this->persistStore('payment-prefill-refund-' . uniqid(), $baseCurrency);
		$order = $this->persistOrder($store, $documentCurrency, '300.0000')
			->setExchangeRateToBase('2.00000000')
			->setPaidAmountBase('120.0000');
		$payment = $this->paymentManager->createForStore($store);

		$this->paymentManager->prefillRefundFromOrder($payment, $order);

		self::assertSame($order, $payment->getOrder());
		self::assertSame($documentCurrency, $payment->getCurrency());
		self::assertSame(PaymentDirectionEnum::OUTGOING, $payment->getDirection());
		self::assertSame(PaymentTypeEnum::CASH, $payment->getType());
		self::assertSame('60.0000', $payment->getAmount());
	}

	public function testPrefillFromPurchaseUsesDocumentCurrencyAndRemainingAmount(): void
	{
		$baseCurrency = $this->persistCurrency('L' . substr(uniqid(), -2), 'Purchase prefill base currency');
		$documentCurrency = $this->persistCurrency('M' . substr(uniqid(), -2), 'Purchase prefill document currency');
		$store = $this->persistStore('payment-prefill-purchase-' . uniqid(), $baseCurrency);
		$purchase = $this->persistPurchase($store, $documentCurrency, '240.0000')
			->setExchangeRateToBase('2.00000000')
			->setPaidAmountBase('40.0000');
		$payment = $this->paymentManager->createForStore($store);

		$this->paymentManager->prefillFromPurchase($payment, $purchase);

		self::assertSame($purchase, $payment->getPurchase());
		self::assertSame($documentCurrency, $payment->getCurrency());
		self::assertSame(PaymentDirectionEnum::OUTGOING, $payment->getDirection());
		self::assertSame('100.0000', $payment->getAmount());
		self::assertSame([
			'direction' => 'outgoing',
			'currency' => $documentCurrency->getCode(),
			'amount' => '100.0000',
		], $this->paymentManager->defaultsForPurchase($purchase));
	}

	public function testReversePaymentCreatesLinkedRefundWithOriginalSnapshotAndRecalculatesOrder(): void
	{
		$baseCurrency = $this->persistCurrency('R' . substr(uniqid(), -2), 'Reversal base currency');
		$paymentCurrency = $this->persistCurrency('V' . substr(uniqid(), -2), 'Reversal payment currency');
		$store = $this->persistStore('payment-reversal-' . uniqid(), $baseCurrency);
		$order = $this->persistOrder($store, $baseCurrency, '100.0000')
			->setStatus(OrderStatus::CONFIRMED);
		$this->persistExchangeRate($paymentCurrency, $baseCurrency, $store, '2.00000000');
		$this->entityManager->flush();

		$payment = $this->paymentManager->createForStore($store)
			->setCurrency($paymentCurrency)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setAmount('50.0000')
			->setPaidAt(new DateTimeImmutable('2026-05-18 10:00:00'));
		$this->paymentManager->savePayment($payment);

		$reversal = $this->paymentManager->reversePayment($payment, 'Original payment was entered by mistake.');

		self::assertSame(PaymentTypeEnum::CASH, $reversal->getType());
		self::assertSame(PaymentDirectionEnum::OUTGOING, $reversal->getDirection());
		self::assertSame('50.0000', $reversal->getAmount());
		self::assertSame('100.0000', $reversal->getAmountBase());
		self::assertSame('2.00000000', $reversal->getExchangeRateToBase());
		self::assertSame('Original payment was entered by mistake.', $reversal->getComment());
		self::assertSame($payment, $reversal->getReversesPayment());
		self::assertSame($reversal, $payment->getReversedByPayment());
		self::assertSame('0.0000', $order->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::REFUNDED, $order->getPaymentStatus());

		$history = $this->entityManager->getRepository(PaymentHistory::class)->findOneBy([
			'payment' => $reversal,
			'order' => $order,
			'eventKey' => 'payment.reversed',
		]);
		self::assertInstanceOf(PaymentHistory::class, $history);
		self::assertSame([
			'original_payment_id' => $payment->getId(),
			'reason' => 'Original payment was entered by mistake.',
		], $history->getPayload());
		self::assertContains('payment.reversed', array_map(
			static fn ($entry): string => $entry->getEventKey(),
			$this->documentTimelineBuilder->forOrder($order),
		));
	}

	public function testPaymentCorrectionCannotBeReversedAgain(): void
	{
		$currency = $this->persistCurrency('Z' . substr(uniqid(), -2), 'Correction reversal currency');
		$store = $this->persistStore('payment-correction-reversal-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '20.0000')
			->setStatus(OrderStatus::CONFIRMED);
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CARD)
			->setAmount('20.0000');
		$this->paymentManager->savePayment($payment);
		$reversal = $this->paymentManager->reversePayment($payment, 'Original payment was entered by mistake.');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Payment correction cannot be reversed by this flow.');

		$this->paymentManager->reversePayment($reversal, 'Do not reverse a correction.');
	}

	public function testOrderPaymentCannotExceedRemainingAmount(): void
	{
		$currency = $this->persistCurrency('E' . substr(uniqid(), -2), 'Payment limit currency');
		$store = $this->persistStore('payment-limit-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '100.0000')
			->setStatus(OrderStatus::CONFIRMED)
			->setPaidAmountBase('40.0000');
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount('61.0000');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Payment amount cannot exceed remaining order amount.');

		$this->paymentManager->savePayment($payment);
	}

	public function testOrderRefundCannotExceedNetPaidAmount(): void
	{
		$currency = $this->persistCurrency('J' . substr(uniqid(), -2), 'Refund limit currency');
		$store = $this->persistStore('refund-limit-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '100.0000')
			->setPaidAmountBase('40.0000');
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::OUTGOING)
			->setType(PaymentTypeEnum::CARD)
			->setAmount('41.0000');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Refund amount cannot exceed net paid order amount.');

		$this->paymentManager->savePayment($payment);
	}

	public function testOutgoingRefundCanBeRecordedForCanceledPaidOrder(): void
	{
		$currency = $this->persistCurrency('U' . substr(uniqid(), -2), 'Canceled refund currency');
		$store = $this->persistStore('canceled-refund-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '100.0000')
			->setStatus(OrderStatus::CANCELED)
			->setPaidAmountBase('40.0000');
		$existingPayment = (new Payment())
			->setStore($store)
			->setOrder($order)
			->setCurrency($currency)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount('40.0000')
			->setAmountBase('40.0000')
			->setPaidAt(new DateTimeImmutable('2026-05-18 09:00:00'));
		$order->addPayment($existingPayment);

		$this->entityManager->persist($existingPayment);
		$this->entityManager->flush();

		$refund = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::OUTGOING)
			->setType(PaymentTypeEnum::CARD)
			->setAmount('40.0000');

		$this->paymentManager->savePayment($refund);

		self::assertSame('0.0000', $order->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::REFUNDED, $order->getPaymentStatus());
	}

	public function testIncomingOrderPaymentRequiresStatusAfterDraftAndBeforeCancellation(): void
	{
		$currency = $this->persistCurrency('K' . substr(uniqid(), -2), 'Payment status guard currency');
		$store = $this->persistStore('payment-status-guard-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '100.0000')
			->setStatus(OrderStatus::DRAFT);
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount('10.0000');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Order payments are allowed only after draft and before cancellation.');

		$this->paymentManager->savePayment($payment);
	}

	public function testIncomingPaymentCannotBeRecordedForCanceledOrder(): void
	{
		$currency = $this->persistCurrency('N' . substr(uniqid(), -2), 'Canceled payment guard currency');
		$store = $this->persistStore('payment-canceled-guard-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '100.0000')
			->setStatus(OrderStatus::CANCELED);
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setDirection(PaymentDirectionEnum::INCOMING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount('10.0000');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Order payments are allowed only after draft and before cancellation.');

		$this->paymentManager->savePayment($payment);
	}

	public function testReversePaymentRequiresReason(): void
	{
		$currency = $this->persistCurrency('Q' . substr(uniqid(), -2), 'Reason currency');
		$store = $this->persistStore('payment-reason-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '10.0000')
			->setStatus(OrderStatus::CONFIRMED);
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setAmount('10.0000');
		$this->paymentManager->savePayment($payment);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Reversal reason is required.');

		$this->paymentManager->reversePayment($payment, '   ');
	}

	public function testRecordedPaymentCannotBeEdited(): void
	{
		$currency = $this->persistCurrency('I' . substr(uniqid(), -2), 'Immutable currency');
		$store = $this->persistStore('payment-immutable-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency, '10.0000')
			->setStatus(OrderStatus::CONFIRMED);
		$this->entityManager->flush();
		$payment = $this->paymentManager->createForStore($store)
			->setOrder($order)
			->setAmount('10.0000');
		$this->paymentManager->savePayment($payment);

		$payment->setAmount('11.0000');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Recorded payments cannot be edited. Use reversal correction flow.');

		$this->paymentManager->savePayment($payment);
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

	private function persistOrder(Store $store, Currency $currency, string $totalAmountBase): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('SO-' . uniqid())
			->setTotalAmount($totalAmountBase)
			->setTotalAmountBase($totalAmountBase);

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		return $order;
	}

	private function persistPurchase(Store $store, Currency $currency, string $totalAmountBase): Purchase
	{
		$purchase = (new Purchase())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('PO-' . uniqid())
			->setTotalAmount($totalAmountBase)
			->setTotalAmountBase($totalAmountBase);

		$this->entityManager->persist($purchase);
		$this->entityManager->flush();

		return $purchase;
	}

	private function persistExchangeRate(Currency $fromCurrency, Currency $toCurrency, Store $store, string $rate): ExchangeRate
	{
		$exchangeRate = (new ExchangeRate())
			->setFromCurrency($fromCurrency)
			->setToCurrency($toCurrency)
			->setStore($store)
			->setRate($rate)
			->setValidFrom(new DateTimeImmutable('2026-01-01 00:00:00'));

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();

		return $exchangeRate;
	}
}

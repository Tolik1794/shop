<?php

namespace App\Tests\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTypeEnum;
use App\Service\Payment\PaymentRecalculationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PaymentRecalculationServiceTest extends TestCase
{
	private PaymentRecalculationService $service;

	protected function setUp(): void
	{
		$this->service = new PaymentRecalculationService();
	}

	public function testOrderMovesThroughPartialPaidAndOverpaidStates(): void
	{
		$order = (new Order())->setTotalAmountBase('100.0000');
		$order->addPayment($this->payment('40.0000', '2026-05-18 10:00:00'));

		$this->service->recalculate($order);

		self::assertSame('40.0000', $order->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::PARTIALLY_PAID, $order->getPaymentStatus());
		self::assertNull($order->getPaidAt());

		$order->addPayment($this->payment('60.0000', '2026-05-18 11:00:00'));
		$this->service->recalculate($order);

		self::assertSame('100.0000', $order->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::PAID, $order->getPaymentStatus());
		self::assertSame('2026-05-18 11:00:00', $order->getPaidAt()?->format('Y-m-d H:i:s'));

		$order->addPayment($this->payment('10.0000', '2026-05-18 12:00:00'));
		$this->service->recalculate($order);

		self::assertSame('110.0000', $order->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::OVERPAID, $order->getPaymentStatus());
		self::assertSame('2026-05-18 11:00:00', $order->getPaidAt()?->format('Y-m-d H:i:s'));
	}

	public function testRefundReturnsPurchaseToRefundedStateAndClearsPaidAt(): void
	{
		$purchase = (new Purchase())->setTotalAmountBase('100.0000');
		$purchase->addPayment($this->payment('100.0000', '2026-05-18 10:00:00'));
		$purchase->addPayment($this->payment('100.0000', '2026-05-18 11:00:00', PaymentTypeEnum::REFUND));

		$this->service->recalculate($purchase);

		self::assertSame('0.0000', $purchase->getPaidAmountBase());
		self::assertSame(PaymentStatusEnum::REFUNDED, $purchase->getPaymentStatus());
		self::assertNull($purchase->getPaidAt());
	}

	private function payment(string $amountBase, string $paidAt, PaymentTypeEnum $type = PaymentTypeEnum::CASH): Payment
	{
		return (new Payment())
			->setType($type)
			->setAmountBase($amountBase)
			->setPaidAt(new DateTimeImmutable($paidAt));
	}
}

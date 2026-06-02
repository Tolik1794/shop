<?php

namespace App\Tests\Service\Payment;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Service\Payment\OrderPaymentReviewService;
use PHPUnit\Framework\TestCase;

class OrderPaymentReviewServiceTest extends TestCase
{
	public function testReturnsReviewForCanceledOrderWithNetPaidAmount(): void
	{
		$review = (new OrderPaymentReviewService())->canceledPaidReview((new Order())
			->setStatus(OrderStatus::CANCELED)
			->setPaidAmountBase('25.5000'));

		self::assertNotNull($review);
		self::assertSame('25.5000', $review->paidAmountBase);
	}

	public function testIgnoresCanceledOrderWithoutNetPaidAmount(): void
	{
		$review = (new OrderPaymentReviewService())->canceledPaidReview((new Order())
			->setStatus(OrderStatus::CANCELED)
			->setPaidAmountBase('0.0000'));

		self::assertNull($review);
	}

	public function testIgnoresActivePaidOrder(): void
	{
		$review = (new OrderPaymentReviewService())->canceledPaidReview((new Order())
			->setStatus(OrderStatus::DELIVERED)
			->setPaidAmountBase('25.5000'));

		self::assertNull($review);
	}

	public function testReturnsIncomingActionForUnpaidOrderRemainder(): void
	{
		$actions = (new OrderPaymentReviewService())->paymentActions((new Order())
			->setStatus(OrderStatus::CONFIRMED)
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('25.0000')
			->setExchangeRateToBase('2.00000000'));

		self::assertNotNull($actions->incoming);
		self::assertSame('37.5000', $actions->incoming->amount);
		self::assertSame('75.0000', $actions->incoming->amountBase);
		self::assertNull($actions->refund);
	}

	public function testReturnsRefundActionForCanceledPaidOrder(): void
	{
		$actions = (new OrderPaymentReviewService())->paymentActions((new Order())
			->setStatus(OrderStatus::CANCELED)
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('80.0000')
			->setExchangeRateToBase('2.00000000'));

		self::assertNotNull($actions->incoming);
		self::assertSame('10.0000', $actions->incoming->amount);
		self::assertNotNull($actions->refund);
		self::assertSame('40.0000', $actions->refund->amount);
		self::assertSame('80.0000', $actions->refund->amountBase);
	}

	public function testDoesNotReturnActionsForFullyPaidActiveOrder(): void
	{
		$actions = (new OrderPaymentReviewService())->paymentActions((new Order())
			->setStatus(OrderStatus::DELIVERED)
			->setTotalAmountBase('100.0000')
			->setPaidAmountBase('100.0000')
			->setExchangeRateToBase('1.00000000'));

		self::assertFalse($actions->hasAny());
	}
}

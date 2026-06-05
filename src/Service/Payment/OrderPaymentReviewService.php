<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\OrderStatus;

class OrderPaymentReviewService
{
	public function __construct(
		private readonly OrderPaymentEligibilityService $orderPaymentEligibilityService,
	)
	{
	}

	public function paymentActions(Order $order): OrderPaymentActions
	{
		$exchangeRateToBase = $this->numberValue($order->getExchangeRateToBase());
		$paidAmountBase = $this->numberValue($order->getPaidAmountBase());
		$unpaidAmountBase = max(0.0, $this->numberValue($order->getTotalAmountBase()) - $paidAmountBase);
		$refundableAmountBase = $order->getStatus() === OrderStatus::CANCELED ? $paidAmountBase : 0.0;

		return new OrderPaymentActions(
			incoming: $this->canRecordIncomingPayment($order) && $unpaidAmountBase > 0.00005 ? $this->action($unpaidAmountBase, $exchangeRateToBase) : null,
			refund: $refundableAmountBase > 0.00005 ? $this->action($refundableAmountBase, $exchangeRateToBase) : null,
		);
	}

	public function canRecordIncomingPayment(Order $order): bool
	{
		return $this->orderPaymentEligibilityService->canRecordIncomingPayment($order);
	}

	public function paymentSummary(Order $order): OrderPaymentSummary
	{
		$exchangeRateToBase = $this->numberValue($order->getExchangeRateToBase());
		$paidAmountBase = $this->numberValue($order->getPaidAmountBase());
		$amountDueBase = max(0.0, $this->numberValue($order->getTotalAmountBase()) - $paidAmountBase);

		return new OrderPaymentSummary(
			amountDue: $this->formatMoney($this->documentAmount($amountDueBase, $exchangeRateToBase)),
			amountDueBase: $this->formatMoney($amountDueBase),
			paidAmount: $this->formatMoney($this->documentAmount($paidAmountBase, $exchangeRateToBase)),
			paidAmountBase: $this->formatMoney($paidAmountBase),
		);
	}

	public function canceledPaidReview(Order $order): ?CanceledOrderPaymentReview
	{
		if ($order->getStatus() !== OrderStatus::CANCELED) {
			return null;
		}

		$paidAmountBase = $this->numberValue($order->getPaidAmountBase());

		if ($paidAmountBase <= 0.00005) {
			return null;
		}

		return new CanceledOrderPaymentReview($this->formatMoney($paidAmountBase));
	}

	private function action(float $amountBase, float $exchangeRateToBase): OrderPaymentAction
	{
		return new OrderPaymentAction(
			amount: $this->formatMoney($exchangeRateToBase > 0 ? $amountBase / $exchangeRateToBase : 0.0),
			amountBase: $this->formatMoney($amountBase),
		);
	}

	private function documentAmount(float $amountBase, float $exchangeRateToBase): float
	{
		return $exchangeRateToBase > 0 ? $amountBase / $exchangeRateToBase : 0.0;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

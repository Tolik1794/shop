<?php

namespace App\Service\Payment;

use App\Entity\Currency;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Store;
use App\Enum\PaymentDirectionEnum;
use App\Service\ExchangeRateResolver;
use RuntimeException;

class PaymentAmountLimitService
{
	public function __construct(
		private readonly ExchangeRateResolver $exchangeRateResolver,
	)
	{
	}

	public function violation(Payment $payment, bool $useStoredAmountBase = false): ?string
	{
		$order = $payment->getOrder();

		if (!$order instanceof Order) {
			return null;
		}

		$amountBase = $this->paymentAmountBase($payment, $useStoredAmountBase);
		$isRefund = $this->isOrderRefund($payment);
		$limitBase = $isRefund
			? $this->numberValue($order->getPaidAmountBase())
			: max(0.0, $this->numberValue($order->getTotalAmountBase()) - $this->numberValue($order->getPaidAmountBase()));

		if ($amountBase > $limitBase + 0.00005) {
			return $isRefund
				? 'Refund amount cannot exceed net paid order amount.'
				: 'Payment amount cannot exceed remaining order amount.';
		}

		return null;
	}

	private function isOrderRefund(Payment $payment): bool
	{
		return $payment->getDirection() === PaymentDirectionEnum::OUTGOING;
	}

	public function assertWithinLimits(Payment $payment, bool $useStoredAmountBase = false): void
	{
		$violation = $this->violation($payment, $useStoredAmountBase);

		if ($violation !== null) {
			throw new RuntimeException($violation);
		}
	}

	private function paymentAmountBase(Payment $payment, bool $useStoredAmountBase): float
	{
		if ($useStoredAmountBase) {
			return $this->numberValue($payment->getAmountBase());
		}

		$currency = $payment->getCurrency();
		$store = $payment->getStore();
		$baseCurrency = $store?->getBaseCurrency();

		if (!$currency instanceof Currency || !$store instanceof Store || !$baseCurrency instanceof Currency) {
			return $this->numberValue($payment->getAmountBase());
		}

		return $this->numberValue($payment->getAmount())
			* $this->numberValue($this->exchangeRateResolver->resolve($currency, $baseCurrency, $store, $payment->getPaidAt()));
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}
}

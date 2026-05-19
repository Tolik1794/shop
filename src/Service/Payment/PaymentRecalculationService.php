<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Enum\PaymentStatusEnum;
use App\Enum\PaymentTypeEnum;

class PaymentRecalculationService
{
	public function recalculate(Order|Purchase $document): void
	{
		$payments = array_values(array_filter(
			$document->getPayments()->toArray(),
			static fn (mixed $payment): bool => $payment instanceof Payment,
		));

		usort($payments, static function (Payment $left, Payment $right): int {
			$paidAtComparison = $left->getPaidAt() <=> $right->getPaidAt();

			return $paidAtComparison !== 0
				? $paidAtComparison
				: (($left->getId() ?? 0) <=> ($right->getId() ?? 0));
		});

		$totalAmountBase = $this->numberValue($document->getTotalAmountBase());
		$effectivePaidAmount = 0.0;
		$hasPositivePayment = false;
		$hasRefund = false;
		$fullyPaidAt = null;

		foreach ($payments as $payment) {
			$amountBase = $this->numberValue($payment->getAmountBase());
			$isRefund = $payment->getType() === PaymentTypeEnum::REFUND;
			$delta = $isRefund ? -$amountBase : $amountBase;
			$effectivePaidAmount += $delta;
			$hasPositivePayment = $hasPositivePayment || $delta > 0;
			$hasRefund = $hasRefund || $isRefund;

			if ($fullyPaidAt === null && $totalAmountBase > 0 && $effectivePaidAmount >= $totalAmountBase) {
				$fullyPaidAt = $payment->getPaidAt();
			}
		}

		$visiblePaidAmount = max(0, $effectivePaidAmount);
		$paymentStatus = $this->resolveStatus(
			paidAmountBase: $visiblePaidAmount,
			totalAmountBase: $totalAmountBase,
			hasPositivePayment: $hasPositivePayment,
			hasRefund: $hasRefund,
		);

		$document
			->setPaidAmountBase($this->formatMoney($visiblePaidAmount))
			->setPaymentStatus($paymentStatus)
			->setPaidAt(in_array($paymentStatus, [PaymentStatusEnum::PAID, PaymentStatusEnum::OVERPAID], true)
				? $fullyPaidAt
				: null);
	}

	private function resolveStatus(
		float $paidAmountBase,
		float $totalAmountBase,
		bool $hasPositivePayment,
		bool $hasRefund,
	): PaymentStatusEnum {
		if ($paidAmountBase <= 0) {
			return $hasPositivePayment && $hasRefund
				? PaymentStatusEnum::REFUNDED
				: PaymentStatusEnum::UNPAID;
		}

		if ($paidAmountBase < $totalAmountBase) {
			return PaymentStatusEnum::PARTIALLY_PAID;
		}

		if ($this->isEqualMoney($paidAmountBase, $totalAmountBase)) {
			return PaymentStatusEnum::PAID;
		}

		return PaymentStatusEnum::OVERPAID;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function isEqualMoney(float $left, float $right): bool
	{
		return abs($left - $right) < 0.00005;
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

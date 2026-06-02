<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Enum\PaymentDirectionEnum;

class OrderPaymentEligibilityService
{
	public const string INCOMING_PAYMENT_STATUS_MESSAGE = 'Order payments are allowed only after draft and before cancellation.';

	public function canRecordIncomingPayment(Order $order): bool
	{
		return !in_array($order->getStatus(), [OrderStatus::DRAFT, OrderStatus::CANCELED], true);
	}

	public function violation(Payment $payment): ?string
	{
		$order = $payment->getOrder();
		if (!$order instanceof Order || $payment->getDirection() !== PaymentDirectionEnum::INCOMING) {
			return null;
		}

		return $this->canRecordIncomingPayment($order)
			? null
			: self::INCOMING_PAYMENT_STATUS_MESSAGE;
	}
}

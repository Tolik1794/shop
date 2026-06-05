<?php

namespace App\Service\Payment;

readonly class OrderPaymentSummary
{
	public function __construct(
		public string $amountDue,
		public string $amountDueBase,
		public string $paidAmount,
		public string $paidAmountBase,
	)
	{
	}
}

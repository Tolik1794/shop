<?php

namespace App\Service\Payment;

readonly class CanceledOrderPaymentReview
{
	public function __construct(
		public string $paidAmountBase,
	)
	{
	}
}

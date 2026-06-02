<?php

namespace App\Service\Payment;

readonly class OrderPaymentAction
{
	public function __construct(
		public string $amount,
		public string $amountBase,
	)
	{
	}
}

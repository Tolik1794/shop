<?php

namespace App\Service\Payment;

readonly class OrderPaymentActions
{
	public function __construct(
		public ?OrderPaymentAction $incoming,
		public ?OrderPaymentAction $refund,
	)
	{
	}

	public function hasAny(): bool
	{
		return $this->incoming !== null || $this->refund !== null;
	}
}

<?php

namespace App\Enum;

enum PaymentStatusEnum: string
{
	case UNPAID = 'unpaid';
	case PARTIALLY_PAID = 'partially_paid';
	case PAID = 'paid';
	case OVERPAID = 'overpaid';
	case REFUNDED = 'refunded';
}

<?php

namespace App\Enum;

enum PaymentTypeEnum: string
{
	case CASH = 'cash';
	case CARD = 'card';
	case BANK_TRANSFER = 'bank_transfer';
	case ONLINE = 'online';
	case REFUND = 'refund';
	case OTHER = 'other';
}

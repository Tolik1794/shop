<?php

namespace App\Enum;

enum TaxAccrualStatusEnum: string
{
	case ACCRUED = 'accrued';
	case PAID = 'paid';
	case OVERDUE = 'overdue';
}

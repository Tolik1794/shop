<?php

namespace App\Enum;

enum IncomeSourceTypeEnum: string
{
	case PAYMENT = 'payment';
	case BANK_STATEMENT_ROW = 'bank_statement_row';
	case MANUAL = 'manual';
}

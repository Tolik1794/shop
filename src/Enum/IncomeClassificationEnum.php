<?php

namespace App\Enum;

enum IncomeClassificationEnum: string
{
	case INCOME = 'income';
	case REFUND = 'refund';
	case NON_INCOME_TRANSFER = 'non_income_transfer';
	case NON_INCOME_OWN_FUNDS = 'non_income_own_funds';
	case NON_INCOME_OTHER = 'non_income_other';
}

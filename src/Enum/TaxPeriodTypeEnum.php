<?php

namespace App\Enum;

enum TaxPeriodTypeEnum: string
{
	case MONTH = 'month';
	case QUARTER = 'quarter';
	case YEAR = 'year';
}

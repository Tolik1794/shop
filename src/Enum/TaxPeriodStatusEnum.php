<?php

namespace App\Enum;

enum TaxPeriodStatusEnum: string
{
	case OPEN = 'open';
	case CLOSED = 'closed';
	case DECLARED = 'declared';
}

<?php

namespace App\Enum;

enum OrderDiscountModeEnum: string
{
	case NONE = 'none';
	case RULE = 'rule';
	case MANUAL_OVERRIDE = 'manual_override';
}

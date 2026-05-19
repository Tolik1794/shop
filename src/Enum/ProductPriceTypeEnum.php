<?php

namespace App\Enum;

enum ProductPriceTypeEnum: string
{
	case REGULAR = 'regular';
	case SALE = 'sale';
	case WHOLESALE = 'wholesale';
	case CUSTOM = 'custom';
}

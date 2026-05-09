<?php

namespace App\Enum;

enum ProductKindEnum: string
{
	case MATERIAL = 'material';
	case FINISHED_PRODUCT = 'finished_product';
	case RESALE_PRODUCT = 'resale_product';
	case SEMI_FINISHED_PRODUCT = 'semi_finished_product';
	case SERVICE = 'service';
}

<?php

namespace App\Entity;

enum OrderEntryFulfillmentSource: string
{
	case STOCK = 'stock';
	case PRODUCTION = 'production';
	case SERVICE = 'service';
}

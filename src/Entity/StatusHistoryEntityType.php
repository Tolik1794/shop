<?php

namespace App\Entity;

enum StatusHistoryEntityType: string
{
	case PURCHASE = 'purchase';
	case INVENTORY_DOCUMENT = 'inventory_document';
	case PRODUCTION_ORDER = 'production_order';
}

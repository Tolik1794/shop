<?php

namespace App\Enum;

enum InventoryReasonType: string
{
	case WRITE_OFF = 'write_off';
	case STOCK_ADJUSTMENT = 'stock_adjustment';
	case RETURN = 'return';
	case PRODUCTION_LOSS = 'production_loss';
	case TRANSFER = 'transfer';
	case INITIAL_STOCK = 'initial_stock';
	case INVENTORY_COUNT = 'inventory_count';
	case DAMAGE = 'damage';
	case OTHER = 'other';
}

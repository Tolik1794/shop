<?php

namespace App\Enum;

enum InventoryDocumentType: string
{
	case PURCHASE_RECEIPT = 'purchase_receipt';
	case SALE_SHIPMENT = 'sale_shipment';
	case CUSTOMER_RETURN = 'customer_return';
	case SUPPLIER_RETURN = 'supplier_return';
	case PRODUCTION = 'production';
	case WRITE_OFF = 'write_off';
	case STOCK_ADJUSTMENT = 'stock_adjustment';
	case TRANSFER = 'transfer';
	case REVERSAL = 'reversal';
}

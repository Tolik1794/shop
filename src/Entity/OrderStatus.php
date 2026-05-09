<?php

namespace App\Entity;

enum OrderStatus: string
{
	case DRAFT = 'draft';
	case CONFIRMED = 'confirmed';
	case AWAITING_STOCK = 'awaiting_stock';
	case READY_TO_SHIP = 'ready_to_ship';
	case PARTIALLY_SHIPPED = 'partially_shipped';
	case SHIPPED = 'shipped';
	case DELIVERED = 'delivered';
	case COMPLETED = 'completed';
	case CANCELED = 'canceled';
	case PARTIALLY_RETURNED = 'partially_returned';
	case RETURNED = 'returned';
}

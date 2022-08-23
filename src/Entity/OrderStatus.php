<?php

namespace App\Entity;

enum OrderStatus: string
{
	case FORM = 'form';
	case PROCESSING = 'processing';
	case SENT = 'sent';
	case DELIVERED = 'delivered';
	case CANCELED = 'canceled';
	case RETURNED = 'returned';
	case PARTIALLY_RETURNED = 'partially returned';
}

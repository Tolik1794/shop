<?php

namespace App\Entity;

enum PurchaseStatus: string
{
	case FORM = 'form';
	case PROCESSING = 'processing';
	case SENT = 'sent';
	case DELIVERED = 'delivered';
	case CANCELED = 'canceled';
	case RETURNED = 'returned';
	case PARTIALLY_RETURNED = 'partially returned';
}

<?php

namespace App\Entity;

enum PurchaseStatus: string
{
	case DRAFT = 'draft';
	case ORDERED = 'ordered';
	case PARTIALLY_RECEIVED = 'partially_received';
	case RECEIVED = 'received';
	case COMPLETED = 'completed';
	case CANCELED = 'canceled';
	case PARTIALLY_RETURNED = 'partially_returned';
	case RETURNED = 'returned';
}

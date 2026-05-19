<?php

namespace App\Entity;

enum StockReservationStatus: string
{
	case ACTIVE = 'active';
	case COMPLETED = 'completed';
	case CANCELED = 'canceled';
	case EXPIRED = 'expired';
}

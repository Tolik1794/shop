<?php

namespace App\Entity;

enum ProductionOrderStatus: string
{
	case DRAFT = 'draft';
	case PLANNED = 'planned';
	case MATERIALS_RESERVED = 'materials_reserved';
	case IN_PROGRESS = 'in_progress';
	case COMPLETED = 'completed';
	case CANCELED = 'canceled';
}

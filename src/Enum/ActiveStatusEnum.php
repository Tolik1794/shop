<?php

namespace App\Enum;

enum ActiveStatusEnum: string
{
	case ACTIVE = 'active';
	case INACTIVE = 'inactive';
	case DELETED = 'deleted';
}

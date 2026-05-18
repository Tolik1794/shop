<?php

namespace App\Entity;

enum OrderHistorySource: string
{
	case MANUAL = 'manual';
	case SYSTEM = 'system';
	case INTEGRATION = 'integration';
}

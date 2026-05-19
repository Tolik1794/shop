<?php

namespace App\Enum;

enum InventoryDocumentStatus: string
{
	case DRAFT = 'draft';
	case POSTED = 'posted';
	case CANCELED = 'canceled';
}

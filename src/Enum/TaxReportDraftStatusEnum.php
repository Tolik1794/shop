<?php

namespace App\Enum;

enum TaxReportDraftStatusEnum: string
{
	case DRAFT = 'draft';
	case EXPORTED = 'exported';
}

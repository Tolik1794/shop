<?php

namespace App\Enum;

enum ProductRelationTypeEnum: string
{
	case ACCESSORY = 'accessory';
	case SPARE_PART = 'spare_part';
	case HARDWARE = 'hardware';
	case RELATED = 'related';

	public function label(): string
	{
		return 'admin.product.relation_type.' . $this->value;
	}
}

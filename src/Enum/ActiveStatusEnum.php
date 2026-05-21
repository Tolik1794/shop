<?php

namespace App\Enum;

enum ActiveStatusEnum: string
{
	case ACTIVE = 'active';
	case INACTIVE = 'inactive';
	case DELETED = 'deleted';

	/**
	 * @return self[]
	 */
	public static function userSelectableCases(): array
	{
		return [
			self::ACTIVE,
			self::INACTIVE,
		];
	}
}

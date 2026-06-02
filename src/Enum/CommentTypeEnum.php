<?php

namespace App\Enum;

enum CommentTypeEnum: string
{
	case GENERAL = 'general';
	case MANAGER = 'manager';
	case WAREHOUSE = 'warehouse';
	case ACCOUNTING = 'accounting';
	case INTERNAL = 'internal';
	case CALL_RESULT = 'call_result';
	case CALLBACK_NEEDED = 'callback_needed';
	case COMPLAINT = 'complaint';

	/**
	 * Permission code that describes the audience a departmental comment is addressed to.
	 * Universal types return null and are relevant to every user who can view the order.
	 */
	public function audiencePermission(): ?string
	{
		return match ($this) {
			self::MANAGER => 'order.edit',
			self::WAREHOUSE => 'warehouse_stock.manage',
			self::ACCOUNTING => 'payment.view',
			default => null,
		};
	}

	public function translationKey(): string
	{
		return 'comment_type.' . $this->value;
	}
}

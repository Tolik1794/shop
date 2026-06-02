<?php

namespace App\Service\Order;

use App\Enum\CommentTypeEnum;

/**
 * Static, code-defined quick-comment templates used to pre-fill the order comment form.
 */
final class CommentTemplateProvider
{
	/**
	 * @return list<array{key: string, label: string, body: string, type: CommentTypeEnum, important: bool}>
	 */
	public function templates(): array
	{
		return [
			$this->template('customer_no_answer', 'Customer did not answer', CommentTypeEnum::CALL_RESULT),
			$this->template('callback_needed', 'Need to call back', CommentTypeEnum::CALLBACK_NEEDED),
			$this->template('customer_confirmed', 'Customer confirmed the order', CommentTypeEnum::CALL_RESULT),
			$this->template('customer_clarify', 'Customer asks to clarify details', CommentTypeEnum::CALL_RESULT),
			$this->template('customer_declined', 'Customer declined the order', CommentTypeEnum::CALL_RESULT, true),
			$this->template('payment_question', 'Payment question', CommentTypeEnum::ACCOUNTING),
			$this->template('delivery_question', 'Delivery question', CommentTypeEnum::WAREHOUSE),
			$this->template('complaint', 'Complaint', CommentTypeEnum::COMPLAINT, true),
			$this->template('important', 'Important', CommentTypeEnum::GENERAL, true),
		];
	}

	/**
	 * @return array{key: string, label: string, body: string, type: CommentTypeEnum, important: bool}
	 */
	private function template(string $key, string $label, CommentTypeEnum $type, bool $important = false): array
	{
		return [
			'key' => $key,
			'label' => $label,
			'body' => $label,
			'type' => $type,
			'important' => $important,
		];
	}
}

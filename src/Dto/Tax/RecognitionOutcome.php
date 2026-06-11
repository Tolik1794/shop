<?php

namespace App\Dto\Tax;

use App\Entity\IncomeRecord;

final readonly class RecognitionOutcome
{
	public const string STATUS_CREATED = 'created';
	public const string STATUS_SKIPPED = 'skipped';

	public const string REASON_NO_LEGAL_ENTITY = 'no_legal_entity';
	public const string REASON_ALREADY_RECOGNIZED = 'already_recognized';
	public const string REASON_MISSING_NBU_RATE = 'missing_nbu_rate';
	public const string REASON_NOT_INCOME_RELEVANT = 'not_income_relevant';
	public const string REASON_UNSAVED_PAYMENT = 'unsaved_payment';

	private function __construct(
		public string $status,
		public ?string $reason,
		public ?IncomeRecord $record,
	)
	{
	}

	public static function created(IncomeRecord $record): self
	{
		return new self(self::STATUS_CREATED, null, $record);
	}

	public static function skipped(string $reason): self
	{
		return new self(self::STATUS_SKIPPED, $reason, null);
	}

	public function isCreated(): bool
	{
		return $this->status === self::STATUS_CREATED;
	}
}

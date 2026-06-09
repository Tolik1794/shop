<?php

namespace App\Exception;

use RuntimeException;

class ProductionDemandException extends RuntimeException
{
	public function __construct(
		string $message,
		private readonly string $translationKey,
		private readonly array $translationParameters = [],
	)
	{
		parent::__construct($message);
	}

	public function getTranslationKey(): string
	{
		return $this->translationKey;
	}

	public function getTranslationParameters(): array
	{
		return $this->translationParameters;
	}
}

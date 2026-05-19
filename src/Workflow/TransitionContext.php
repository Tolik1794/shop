<?php

namespace App\Workflow;

use App\Entity\User\User;
use DateTimeImmutable;

class TransitionContext
{
	/**
	 * @param array<string, mixed> $payload
	 */
	public function __construct(
		public readonly ?User $actor,
		public readonly string $source,
		public readonly DateTimeImmutable $occurredAt,
		public readonly ?string $comment = null,
		public readonly array $payload = [],
		public readonly ?string $correlationId = null,
	)
	{
	}

	/**
	 * Creates a context for automated system-driven transitions.
	 *
	 * @param array<string, mixed> $payload
	 */
	public static function system(
		array $payload = [],
		?string $comment = null,
		?DateTimeImmutable $occurredAt = null,
		?string $correlationId = null,
	): self
	{
		return new self(
			actor: null,
			source: 'system',
			occurredAt: $occurredAt ?? new DateTimeImmutable(),
			comment: $comment,
			payload: $payload,
			correlationId: $correlationId,
		);
	}

	/**
	 * Creates a context for user-driven transitions.
	 *
	 * @param array<string, mixed> $payload
	 */
	public static function manual(
		User $actor,
		array $payload = [],
		?string $comment = null,
		?DateTimeImmutable $occurredAt = null,
		?string $correlationId = null,
	): self
	{
		return new self(
			actor: $actor,
			source: 'manual',
			occurredAt: $occurredAt ?? new DateTimeImmutable(),
			comment: $comment,
			payload: $payload,
			correlationId: $correlationId,
		);
	}
}

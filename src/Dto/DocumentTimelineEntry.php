<?php

namespace App\Dto;

use DateTimeImmutable;

class DocumentTimelineEntry
{
	/**
	 * @param array<string, array{from: mixed, to: mixed}>|null $changes
	 */
	public function __construct(
		private readonly DateTimeImmutable $occurredAt,
		private readonly string $title,
		private readonly ?string $source,
		private readonly string $eventKey,
		private readonly ?string $actorName,
		private readonly ?string $description = null,
		private readonly ?array $changes = null,
		private readonly int $sortId = 0,
	)
	{
	}

	public function getOccurredAt(): DateTimeImmutable { return $this->occurredAt; }
	public function getTitle(): string { return $this->title; }
	public function getSource(): ?string { return $this->source; }
	public function getEventKey(): string { return $this->eventKey; }
	public function getActorName(): ?string { return $this->actorName; }
	public function getDescription(): ?string { return $this->description; }
	public function getChanges(): ?array { return $this->changes; }
	public function getSortId(): int { return $this->sortId; }
}

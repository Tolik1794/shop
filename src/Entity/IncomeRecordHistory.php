<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Repository\IncomeRecordHistoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IncomeRecordHistoryRepository::class)]
#[ORM\Index(name: 'idx_income_record_history_record_occurred_at', columns: ['income_record_id', 'occurred_at'])]
class IncomeRecordHistory
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?IncomeRecord $incomeRecord = null;

	#[ORM\Column(length: 100)]
	private string $eventKey;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $payload = null;

	#[ORM\Column]
	private DateTimeImmutable $occurredAt;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $actorNameSnapshot = null;

	#[ORM\ManyToOne]
	private ?User $actor = null;

	public function __construct()
	{
		$this->occurredAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getIncomeRecord(): ?IncomeRecord
	{
		return $this->incomeRecord;
	}

	public function setIncomeRecord(?IncomeRecord $incomeRecord): self
	{
		$this->incomeRecord = $incomeRecord;

		return $this;
	}

	public function getEventKey(): string
	{
		return $this->eventKey;
	}

	public function setEventKey(string $eventKey): self
	{
		$this->eventKey = $eventKey;

		return $this;
	}

	public function getPayload(): ?array
	{
		return $this->payload;
	}

	public function setPayload(?array $payload): self
	{
		$this->payload = $payload;

		return $this;
	}

	public function getOccurredAt(): DateTimeImmutable
	{
		return $this->occurredAt;
	}

	public function setOccurredAt(DateTimeImmutable $occurredAt): self
	{
		$this->occurredAt = $occurredAt;

		return $this;
	}

	public function getActorNameSnapshot(): ?string
	{
		return $this->actorNameSnapshot;
	}

	public function setActorNameSnapshot(?string $actorNameSnapshot): self
	{
		$this->actorNameSnapshot = $actorNameSnapshot;

		return $this;
	}

	public function getActor(): ?User
	{
		return $this->actor;
	}

	public function setActor(?User $actor): self
	{
		$this->actor = $actor;

		return $this;
	}
}

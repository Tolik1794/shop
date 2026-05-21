<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Repository\StatusHistoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusHistoryRepository::class)]
#[ORM\Index(name: 'IDX_6B6D4D3110DAF24A', columns: ['changed_by_id'])]
#[ORM\Index(name: 'IDX_6B6D4D31B092A811', columns: ['store_id'])]
#[ORM\Index(name: 'idx_status_history_store_entity_changed_at', columns: ['store_id', 'entity_type', 'entity_id', 'changed_at'])]
#[ORM\Index(name: 'idx_status_history_store_changed_at', columns: ['store_id', 'changed_at'])]
class StatusHistory
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(name: 'entity_type', length: 100, enumType: StatusHistoryEntityType::class)]
	private StatusHistoryEntityType $entityType;

	#[ORM\Column(name: 'entity_id')]
	private int $entityId;

	#[ORM\Column(length: 100, nullable: true)]
	private ?string $oldStatus = null;

	#[ORM\Column(length: 100)]
	private string $newStatus;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column(name: 'changed_at')]
	private DateTimeImmutable $changedAt;

	#[ORM\ManyToOne]
	private ?User $changedBy = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private Store $store;

	public function __construct()
	{
		$this->changedAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getEntityType(): StatusHistoryEntityType { return $this->entityType; }
	public function setEntityType(StatusHistoryEntityType $entityType): self { $this->entityType = $entityType; return $this; }
	public function getEntityId(): int { return $this->entityId; }
	public function setEntityId(int $entityId): self { $this->entityId = $entityId; return $this; }
	public function getOldStatus(): ?string { return $this->oldStatus; }
	public function setOldStatus(?string $oldStatus): self { $this->oldStatus = $oldStatus; return $this; }
	public function getNewStatus(): string { return $this->newStatus; }
	public function setNewStatus(string $newStatus): self { $this->newStatus = $newStatus; return $this; }
	public function getComment(): ?string { return $this->comment; }
	public function setComment(?string $comment): self { $this->comment = $comment; return $this; }
	public function getChangedAt(): DateTimeImmutable { return $this->changedAt; }
	public function setChangedAt(DateTimeImmutable $changedAt): self { $this->changedAt = $changedAt; return $this; }
	public function getChangedBy(): ?User { return $this->changedBy; }
	public function setChangedBy(?User $changedBy): self { $this->changedBy = $changedBy; return $this; }
	public function getStore(): Store { return $this->store; }
	public function setStore(Store $store): self { $this->store = $store; return $this; }
}

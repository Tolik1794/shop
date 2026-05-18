<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Repository\OrderHistoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderHistoryRepository::class)]
class OrderHistory
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 100)]
	private string $eventKey;

	#[ORM\Column(length: 32, enumType: OrderHistorySource::class)]
	private OrderHistorySource $source;

	#[ORM\Column(length: 255)]
	private string $title;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $description = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $changes = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $payload = null;

	#[ORM\Column(length: 100, nullable: true)]
	private ?string $relatedEntityType = null;

	#[ORM\Column(nullable: true)]
	private ?int $relatedEntityId = null;

	#[ORM\Column]
	private DateTimeImmutable $occurredAt;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $actorNameSnapshot = null;

	#[ORM\ManyToOne(inversedBy: 'historyEntries')]
	#[ORM\JoinColumn(nullable: false)]
	private Order $order;

	#[ORM\ManyToOne]
	private ?User $actor = null;

	public function __construct()
	{
		$this->occurredAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getEventKey(): string { return $this->eventKey; }
	public function setEventKey(string $eventKey): self { $this->eventKey = $eventKey; return $this; }
	public function getSource(): OrderHistorySource { return $this->source; }
	public function setSource(OrderHistorySource $source): self { $this->source = $source; return $this; }
	public function getTitle(): string { return $this->title; }
	public function setTitle(string $title): self { $this->title = $title; return $this; }
	public function getDescription(): ?string { return $this->description; }
	public function setDescription(?string $description): self { $this->description = $description; return $this; }
	public function getChanges(): ?array { return $this->changes; }
	public function setChanges(?array $changes): self { $this->changes = $changes; return $this; }
	public function getPayload(): ?array { return $this->payload; }
	public function setPayload(?array $payload): self { $this->payload = $payload; return $this; }
	public function getRelatedEntityType(): ?string { return $this->relatedEntityType; }
	public function setRelatedEntityType(?string $relatedEntityType): self { $this->relatedEntityType = $relatedEntityType; return $this; }
	public function getRelatedEntityId(): ?int { return $this->relatedEntityId; }
	public function setRelatedEntityId(?int $relatedEntityId): self { $this->relatedEntityId = $relatedEntityId; return $this; }
	public function getOccurredAt(): DateTimeImmutable { return $this->occurredAt; }
	public function setOccurredAt(DateTimeImmutable $occurredAt): self { $this->occurredAt = $occurredAt; return $this; }
	public function getActorNameSnapshot(): ?string { return $this->actorNameSnapshot; }
	public function setActorNameSnapshot(?string $actorNameSnapshot): self { $this->actorNameSnapshot = $actorNameSnapshot; return $this; }
	public function getOrder(): Order { return $this->order; }
	public function setOrder(Order $order): self { $this->order = $order; return $this; }
	public function getActor(): ?User { return $this->actor; }
	public function setActor(?User $actor): self { $this->actor = $actor; return $this; }
}

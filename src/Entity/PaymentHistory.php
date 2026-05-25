<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use App\Repository\PaymentHistoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentHistoryRepository::class)]
#[ORM\Index(name: 'idx_payment_history_store_document_occurred_at', columns: ['store_id', 'document_type', 'document_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_payment_history_store_occurred_at', columns: ['store_id', 'occurred_at'])]
class PaymentHistory
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 100)]
	private string $eventKey;

	#[ORM\Column(length: 32)]
	private string $documentType;

	#[ORM\Column]
	private int $documentId;

	#[ORM\Column(length: 255)]
	private string $title;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $description = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $amount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $amountBase = null;

	#[ORM\Column(length: 3)]
	private string $currencyCode;

	#[ORM\Column(length: 255, enumType: PaymentDirectionEnum::class)]
	private PaymentDirectionEnum $direction;

	#[ORM\Column(length: 255, enumType: PaymentTypeEnum::class)]
	private PaymentTypeEnum $type;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $externalReference = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $payload = null;

	#[ORM\Column]
	private DateTimeImmutable $occurredAt;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $actorNameSnapshot = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private Store $store;

	#[ORM\ManyToOne]
	private ?Order $order = null;

	#[ORM\ManyToOne]
	private ?Purchase $purchase = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private Payment $payment;

	#[ORM\ManyToOne]
	private ?User $actor = null;

	public function __construct()
	{
		$this->occurredAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getEventKey(): string { return $this->eventKey; }
	public function setEventKey(string $eventKey): self { $this->eventKey = $eventKey; return $this; }
	public function getDocumentType(): string { return $this->documentType; }
	public function setDocumentType(string $documentType): self { $this->documentType = $documentType; return $this; }
	public function getDocumentId(): int { return $this->documentId; }
	public function setDocumentId(int $documentId): self { $this->documentId = $documentId; return $this; }
	public function getTitle(): string { return $this->title; }
	public function setTitle(string $title): self { $this->title = $title; return $this; }
	public function getDescription(): ?string { return $this->description; }
	public function setDescription(?string $description): self { $this->description = $description; return $this; }
	public function getAmount(): ?string { return $this->amount; }
	public function setAmount(string $amount): self { $this->amount = $amount; return $this; }
	public function getAmountBase(): ?string { return $this->amountBase; }
	public function setAmountBase(string $amountBase): self { $this->amountBase = $amountBase; return $this; }
	public function getCurrencyCode(): string { return $this->currencyCode; }
	public function setCurrencyCode(string $currencyCode): self { $this->currencyCode = $currencyCode; return $this; }
	public function getDirection(): PaymentDirectionEnum { return $this->direction; }
	public function setDirection(PaymentDirectionEnum $direction): self { $this->direction = $direction; return $this; }
	public function getType(): PaymentTypeEnum { return $this->type; }
	public function setType(PaymentTypeEnum $type): self { $this->type = $type; return $this; }
	public function getExternalReference(): ?string { return $this->externalReference; }
	public function setExternalReference(?string $externalReference): self { $this->externalReference = $externalReference; return $this; }
	public function getPayload(): ?array { return $this->payload; }
	public function setPayload(?array $payload): self { $this->payload = $payload; return $this; }
	public function getOccurredAt(): DateTimeImmutable { return $this->occurredAt; }
	public function setOccurredAt(DateTimeImmutable $occurredAt): self { $this->occurredAt = $occurredAt; return $this; }
	public function getActorNameSnapshot(): ?string { return $this->actorNameSnapshot; }
	public function setActorNameSnapshot(?string $actorNameSnapshot): self { $this->actorNameSnapshot = $actorNameSnapshot; return $this; }
	public function getStore(): Store { return $this->store; }
	public function setStore(Store $store): self { $this->store = $store; return $this; }
	public function getOrder(): ?Order { return $this->order; }
	public function setOrder(?Order $order): self { $this->order = $order; return $this; }
	public function getPurchase(): ?Purchase { return $this->purchase; }
	public function setPurchase(?Purchase $purchase): self { $this->purchase = $purchase; return $this; }
	public function getPayment(): Payment { return $this->payment; }
	public function setPayment(Payment $payment): self { $this->payment = $payment; return $this; }
	public function getActor(): ?User { return $this->actor; }
	public function setActor(?User $actor): self { $this->actor = $actor; return $this; }
}

<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Repository\InventoryDocumentRepository;
use App\Workflow\WorkflowSubjectInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryDocumentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_inventory_document_store_number', columns: ['store_id', 'number'])]
#[ORM\Index(name: 'idx_inventory_document_store_status', columns: ['store_id', 'status'])]
#[ORM\Index(name: 'idx_inventory_document_store_type', columns: ['store_id', 'type'])]
#[ORM\Index(name: 'idx_inventory_document_document_date', columns: ['document_date'])]
class InventoryDocument implements WorkflowSubjectInterface
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $number = null;

	#[ORM\Column(length: 255, enumType: InventoryDocumentType::class)]
	private InventoryDocumentType $type;

	#[ORM\Column(length: 255, enumType: InventoryDocumentStatus::class)]
	private InventoryDocumentStatus $status;

	#[ORM\Column]
	private DateTimeImmutable $documentDate;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: true)]
	private ?Currency $currency = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: true)]
	private ?string $exchangeRateToBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $totalAmount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $totalAmountBase = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $postedAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $canceledAt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToOne(inversedBy: 'inventoryDocuments')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\ManyToOne]
	private ?User $postedBy = null;

	#[ORM\ManyToOne]
	private ?User $canceledBy = null;

	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'reversalDocuments')]
	private ?self $reversedDocument = null;

	/**
	 * @var Collection<int, InventoryDocument>
	 */
	#[ORM\OneToMany(mappedBy: 'reversedDocument', targetEntity: self::class)]
	private Collection $reversalDocuments;

	#[ORM\ManyToOne(inversedBy: 'inventoryDocuments')]
	private ?Order $order = null;

	#[ORM\ManyToOne(inversedBy: 'inventoryDocuments')]
	private ?Purchase $purchase = null;

	#[ORM\ManyToOne(inversedBy: 'inventoryDocuments')]
	private ?ProductionOrder $productionOrder = null;

	#[ORM\ManyToOne(inversedBy: 'inventoryDocuments')]
	private ?InventoryReason $reason = null;

	/**
	 * @var Collection<int, InventoryDocumentLine>
	 */
	#[ORM\OneToMany(mappedBy: 'inventoryDocument', targetEntity: InventoryDocumentLine::class, cascade: ['persist'], orphanRemoval: true)]
	private Collection $lines;

	public function __construct()
	{
		$this->type = InventoryDocumentType::STOCK_ADJUSTMENT;
		$this->status = InventoryDocumentStatus::DRAFT;
		$this->documentDate = new DateTimeImmutable();
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->reversalDocuments = new ArrayCollection();
		$this->lines = new ArrayCollection();
	}

	public function getId(): ?int { return $this->id; }
	public function getNumber(): ?string { return $this->number; }
	public function setNumber(string $number): self { $this->number = $number; return $this; }
	public function getType(): InventoryDocumentType { return $this->type; }
	public function setType(InventoryDocumentType $type): self { $this->type = $type; return $this; }
	public function getStatus(): InventoryDocumentStatus { return $this->status; }
	public function setStatus(InventoryDocumentStatus $status): self { $this->status = $status; return $this; }
	public function getWorkflowKey(): string { return 'inventory_document'; }
	public function getStatusValue(): string { return $this->status->value; }
	public function setStatusValue(string $status): void { $this->status = InventoryDocumentStatus::from($status); }
	public function getDocumentDate(): DateTimeImmutable { return $this->documentDate; }
	public function setDocumentDate(DateTimeImmutable $documentDate): self { $this->documentDate = $documentDate; return $this; }
	public function getCurrency(): ?Currency { return $this->currency; }
	public function setCurrency(?Currency $currency): self { $this->currency = $currency; return $this; }
	public function getExchangeRateToBase(): ?string { return $this->exchangeRateToBase; }
	public function setExchangeRateToBase(?string $exchangeRateToBase): self { $this->exchangeRateToBase = $exchangeRateToBase; return $this; }
	public function getTotalAmount(): ?string { return $this->totalAmount; }
	public function setTotalAmount(?string $totalAmount): self { $this->totalAmount = $totalAmount; return $this; }
	public function getTotalAmountBase(): ?string { return $this->totalAmountBase; }
	public function setTotalAmountBase(?string $totalAmountBase): self { $this->totalAmountBase = $totalAmountBase; return $this; }
	public function getPostedAt(): ?DateTimeImmutable { return $this->postedAt; }
	public function setPostedAt(?DateTimeImmutable $postedAt): self { $this->postedAt = $postedAt; return $this; }
	public function getCanceledAt(): ?DateTimeImmutable { return $this->canceledAt; }
	public function setCanceledAt(?DateTimeImmutable $canceledAt): self { $this->canceledAt = $canceledAt; return $this; }
	public function getComment(): ?string { return $this->comment; }
	public function setComment(?string $comment): self { $this->comment = $comment; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
	public function setUpdatedAt(DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
	public function getStore(): ?Store { return $this->store; }
	public function setStore(?Store $store): self { $this->store = $store; return $this; }
	public function getCreatedBy(): ?User { return $this->createdBy; }
	public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
	public function getUpdatedBy(): ?User { return $this->updatedBy; }
	public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }
	public function getPostedBy(): ?User { return $this->postedBy; }
	public function setPostedBy(?User $postedBy): self { $this->postedBy = $postedBy; return $this; }
	public function getCanceledBy(): ?User { return $this->canceledBy; }
	public function setCanceledBy(?User $canceledBy): self { $this->canceledBy = $canceledBy; return $this; }
	public function getReversedDocument(): ?self { return $this->reversedDocument; }
	public function setReversedDocument(?self $reversedDocument): self { $this->reversedDocument = $reversedDocument; return $this; }
	public function getOrder(): ?Order { return $this->order; }
	public function setOrder(?Order $order): self { $this->order = $order; return $this; }
	public function getPurchase(): ?Purchase { return $this->purchase; }
	public function setPurchase(?Purchase $purchase): self { $this->purchase = $purchase; return $this; }
	public function getProductionOrder(): ?ProductionOrder { return $this->productionOrder; }
	public function setProductionOrder(?ProductionOrder $productionOrder): self
	{
		$this->productionOrder = $productionOrder;

		if ($productionOrder instanceof ProductionOrder && !$productionOrder->getInventoryDocuments()->contains($this)) {
			$productionOrder->addInventoryDocument($this);
		}

		return $this;
	}
	public function getReason(): ?InventoryReason { return $this->reason; }
	public function setReason(?InventoryReason $reason): self { $this->reason = $reason; return $this; }

	/**
	 * @return Collection<int, InventoryDocument>
	 */
	public function getReversalDocuments(): Collection
	{
		return $this->reversalDocuments;
	}

	/**
	 * @return Collection<int, InventoryDocumentLine>
	 */
	public function getLines(): Collection
	{
		return $this->lines;
	}

	public function addLine(InventoryDocumentLine $line): self
	{
		if (!$this->lines->contains($line)) {
			$this->lines->add($line);
			$line->setInventoryDocument($this);
		}

		return $this;
	}

	public function removeLine(InventoryDocumentLine $line): self
	{
		if ($this->lines->removeElement($line) && $line->getInventoryDocument() === $this) {
			$line->setInventoryDocument(null);
		}

		return $this;
	}
}

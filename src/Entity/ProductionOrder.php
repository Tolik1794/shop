<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Repository\ProductionOrderRepository;
use App\Workflow\WorkflowSubjectInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionOrderRepository::class)]
#[ORM\Index(name: 'idx_production_order_store_status', columns: ['store_id', 'status'])]
#[ORM\Index(name: 'idx_production_order_planned_start', columns: ['planned_start_at'])]
class ProductionOrder implements WorkflowSubjectInterface
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255, enumType: ProductionOrderStatus::class)]
	private ProductionOrderStatus $status;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $plannedQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $completedQuantity = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $plannedStartAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $plannedEndAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $startedAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $completedAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $canceledAt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\ManyToOne]
	private ?User $startedBy = null;

	#[ORM\ManyToOne]
	private ?User $completedBy = null;

	#[ORM\ManyToOne]
	private ?User $canceledBy = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	#[ORM\ManyToOne]
	private ?Warehouse $warehouse = null;

	#[ORM\ManyToOne(inversedBy: 'productionOrders')]
	private ?ProductionRecipe $recipe = null;

	/**
	 * @var Collection<int, ProductionOrderMaterial>
	 */
	#[ORM\OneToMany(mappedBy: 'productionOrder', targetEntity: ProductionOrderMaterial::class, cascade: ['persist'], orphanRemoval: true)]
	private Collection $materials;

	/**
	 * @var Collection<int, InventoryDocument>
	 */
	#[ORM\OneToMany(mappedBy: 'productionOrder', targetEntity: InventoryDocument::class)]
	private Collection $inventoryDocuments;

	public function __construct()
	{
		$this->status = ProductionOrderStatus::DRAFT;
		$this->plannedQuantity = '0.0000';
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->materials = new ArrayCollection();
		$this->inventoryDocuments = new ArrayCollection();
	}

	public function getId(): ?int { return $this->id; }
	public function getStatus(): ProductionOrderStatus { return $this->status; }
	public function setStatus(ProductionOrderStatus $status): self { $this->status = $status; return $this; }
	public function getWorkflowKey(): string { return 'production_order'; }
	public function getStatusValue(): string { return $this->status->value; }
	public function setStatusValue(string $status): void { $this->status = ProductionOrderStatus::from($status); }
	public function getPlannedQuantity(): ?string { return $this->plannedQuantity; }
	public function setPlannedQuantity(string $plannedQuantity): self { $this->plannedQuantity = $plannedQuantity; return $this; }
	public function getCompletedQuantity(): ?string { return $this->completedQuantity; }
	public function setCompletedQuantity(?string $completedQuantity): self { $this->completedQuantity = $completedQuantity; return $this; }
	public function getPlannedStartAt(): ?DateTimeImmutable { return $this->plannedStartAt; }
	public function setPlannedStartAt(?DateTimeImmutable $plannedStartAt): self { $this->plannedStartAt = $plannedStartAt; return $this; }
	public function getPlannedEndAt(): ?DateTimeImmutable { return $this->plannedEndAt; }
	public function setPlannedEndAt(?DateTimeImmutable $plannedEndAt): self { $this->plannedEndAt = $plannedEndAt; return $this; }
	public function getStartedAt(): ?DateTimeImmutable { return $this->startedAt; }
	public function setStartedAt(?DateTimeImmutable $startedAt): self { $this->startedAt = $startedAt; return $this; }
	public function getCompletedAt(): ?DateTimeImmutable { return $this->completedAt; }
	public function setCompletedAt(?DateTimeImmutable $completedAt): self { $this->completedAt = $completedAt; return $this; }
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
	public function getStartedBy(): ?User { return $this->startedBy; }
	public function setStartedBy(?User $startedBy): self { $this->startedBy = $startedBy; return $this; }
	public function getCompletedBy(): ?User { return $this->completedBy; }
	public function setCompletedBy(?User $completedBy): self { $this->completedBy = $completedBy; return $this; }
	public function getCanceledBy(): ?User { return $this->canceledBy; }
	public function setCanceledBy(?User $canceledBy): self { $this->canceledBy = $canceledBy; return $this; }
	public function getProduct(): ?Product { return $this->product; }
	public function setProduct(?Product $product): self { $this->product = $product; return $this; }
	public function getWarehouse(): ?Warehouse { return $this->warehouse; }
	public function setWarehouse(?Warehouse $warehouse): self { $this->warehouse = $warehouse; return $this; }
	public function getRecipe(): ?ProductionRecipe { return $this->recipe; }
	public function setRecipe(?ProductionRecipe $recipe): self { $this->recipe = $recipe; return $this; }

	/**
	 * @return Collection<int, ProductionOrderMaterial>
	 */
	public function getMaterials(): Collection
	{
		return $this->materials;
	}

	public function addMaterial(ProductionOrderMaterial $material): self
	{
		if (!$this->materials->contains($material)) {
			$this->materials->add($material);
			$material->setProductionOrder($this);
		}

		return $this;
	}

	public function removeMaterial(ProductionOrderMaterial $material): self
	{
		if ($this->materials->removeElement($material) && $material->getProductionOrder() === $this) {
			$material->setProductionOrder(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, InventoryDocument>
	 */
	public function getInventoryDocuments(): Collection
	{
		return $this->inventoryDocuments;
	}

	public function addInventoryDocument(InventoryDocument $inventoryDocument): self
	{
		if (!$this->inventoryDocuments->contains($inventoryDocument)) {
			$this->inventoryDocuments->add($inventoryDocument);
			$inventoryDocument->setProductionOrder($this);
		}

		return $this;
	}

	public function removeInventoryDocument(InventoryDocument $inventoryDocument): self
	{
		if ($this->inventoryDocuments->removeElement($inventoryDocument) && $inventoryDocument->getProductionOrder() === $this) {
			$inventoryDocument->setProductionOrder(null);
		}

		return $this;
	}
}

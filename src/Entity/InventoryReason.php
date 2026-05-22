<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType;
use App\Repository\InventoryReasonRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: InventoryReasonRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_inventory_reason_store_type_name', columns: ['store_id', 'type', 'name'])]
#[UniqueEntity(
	fields: ['store', 'type', 'name'],
	errorPath: 'name',
	message: 'There is already an inventory reason with this type and name.'
)]
class InventoryReason
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(length: 255, enumType: InventoryReasonType::class)]
	private InventoryReasonType $type;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

	#[ORM\ManyToOne(inversedBy: 'inventoryReasons')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	/**
	 * @var Collection<int, InventoryDocument>
	 */
	#[ORM\OneToMany(mappedBy: 'reason', targetEntity: InventoryDocument::class)]
	private Collection $inventoryDocuments;

	public function __construct()
	{
		$this->type = InventoryReasonType::OTHER;
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->inventoryDocuments = new ArrayCollection();
	}

	public function getId(): ?int { return $this->id; }
	public function getName(): ?string { return $this->name; }
	public function setName(string $name): self { $this->name = $name; return $this; }
	public function getType(): InventoryReasonType { return $this->type; }
	public function setType(InventoryReasonType $type): self { $this->type = $type; return $this; }
	public function getStatus(): ActiveStatusEnum { return $this->status; }
	public function setStatus(ActiveStatusEnum $status): self { $this->status = $status; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
	public function setUpdatedAt(DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
	public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
	public function setDeletedAt(?DateTimeImmutable $deletedAt): self { $this->deletedAt = $deletedAt; return $this; }
	public function getStore(): ?Store { return $this->store; }
	public function setStore(?Store $store): self { $this->store = $store; return $this; }
	public function getCreatedBy(): ?User { return $this->createdBy; }
	public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
	public function getUpdatedBy(): ?User { return $this->updatedBy; }
	public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }

	/**
	 * @return Collection<int, InventoryDocument>
	 */
	public function getInventoryDocuments(): Collection
	{
		return $this->inventoryDocuments;
	}
}

<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Repository\ProductDiscountRuleRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductDiscountRuleRepository::class)]
#[ORM\Table(name: 'product_discount_rule')]
#[ORM\Index(name: 'idx_product_discount_rule_store_status', columns: ['store_id', 'status'])]
class ProductDiscountRule
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 4)]
	private ?string $percent = null;

	#[ORM\Column]
	private bool $isDefault = false;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

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

	/**
	 * @var Collection<int, ProductDiscountTarget>
	 */
	#[ORM\OneToMany(mappedBy: 'rule', targetEntity: ProductDiscountTarget::class, cascade: ['persist'], orphanRemoval: true)]
	private Collection $targets;

	/**
	 * @var Collection<int, OrderEntry>
	 */
	#[ORM\OneToMany(mappedBy: 'discountRule', targetEntity: OrderEntry::class)]
	private Collection $orderEntries;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->targets = new ArrayCollection();
		$this->orderEntries = new ArrayCollection();
	}

	public function __toString(): string
	{
		return sprintf('%s (%s%%)', (string) $this->name, (string) $this->percent);
	}

	public function getId(): ?int { return $this->id; }
	public function getName(): ?string { return $this->name; }
	public function setName(string $name): self { $this->name = $name; return $this; }
	public function getPercent(): ?string { return $this->percent; }
	public function setPercent(string $percent): self { $this->percent = $percent; return $this; }
	public function isDefault(): bool { return $this->isDefault; }
	public function setIsDefault(bool $isDefault): self { $this->isDefault = $isDefault; return $this; }
	public function getStatus(): ActiveStatusEnum { return $this->status; }
	public function setStatus(ActiveStatusEnum $status): self { $this->status = $status; return $this; }
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

	/**
	 * @return Collection<int, ProductDiscountTarget>
	 */
	public function getTargets(): Collection
	{
		return $this->targets;
	}

	public function addTarget(ProductDiscountTarget $target): self
	{
		if (!$this->targets->contains($target)) {
			$this->targets->add($target);
			$target->setRule($this);
		}

		return $this;
	}

	public function removeTarget(ProductDiscountTarget $target): self
	{
		if ($this->targets->removeElement($target) && $target->getRule() === $this) {
			$target->setRule(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, OrderEntry>
	 */
	public function getOrderEntries(): Collection
	{
		return $this->orderEntries;
	}
}

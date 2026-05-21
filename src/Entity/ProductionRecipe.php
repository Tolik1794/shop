<?php

namespace App\Entity;

use App\Enum\ActiveStatusEnum;
use App\Repository\ProductionRecipeRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionRecipeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_production_recipe_store_product_default', columns: ['store_id', 'product_id'], options: ['where' => '(is_default = true)'])]
#[ORM\Index(name: 'idx_production_recipe_store_status', columns: ['store_id', 'status'])]
class ProductionRecipe
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

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
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	/**
	 * @var Collection<int, ProductionRecipeItem>
	 */
	#[ORM\OneToMany(mappedBy: 'recipe', targetEntity: ProductionRecipeItem::class, cascade: ['persist'], orphanRemoval: true)]
	private Collection $items;

	/**
	 * @var Collection<int, ProductionOrder>
	 */
	#[ORM\OneToMany(mappedBy: 'recipe', targetEntity: ProductionOrder::class)]
	private Collection $productionOrders;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->items = new ArrayCollection();
		$this->productionOrders = new ArrayCollection();
	}

	public function getId(): ?int { return $this->id; }
	public function getName(): ?string { return $this->name; }
	public function setName(string $name): self { $this->name = $name; return $this; }
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
	public function getProduct(): ?Product { return $this->product; }
	public function setProduct(?Product $product): self { $this->product = $product; return $this; }

	/**
	 * @return Collection<int, ProductionRecipeItem>
	 */
	public function getItems(): Collection
	{
		return $this->items;
	}

	public function addItem(ProductionRecipeItem $item): self
	{
		if (!$this->items->contains($item)) {
			$this->items->add($item);
			$item->setRecipe($this);
		}

		return $this;
	}

	public function removeItem(ProductionRecipeItem $item): self
	{
		if ($this->items->removeElement($item) && $item->getRecipe() === $this) {
			$item->setRecipe(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, ProductionOrder>
	 */
	public function getProductionOrders(): Collection
	{
		return $this->productionOrders;
	}
}

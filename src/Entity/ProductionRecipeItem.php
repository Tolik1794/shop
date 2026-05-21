<?php

namespace App\Entity;

use App\Repository\ProductionRecipeItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionRecipeItemRepository::class)]
#[ORM\Index(name: 'idx_production_recipe_item_material', columns: ['material_id'])]
class ProductionRecipeItem
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $quantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
	private ?string $wastePercent = null;

	#[ORM\ManyToOne(inversedBy: 'items')]
	#[ORM\JoinColumn(nullable: false)]
	private ?ProductionRecipe $recipe = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $material = null;

	/**
	 * @var Collection<int, ProductionOrderMaterial>
	 */
	#[ORM\OneToMany(mappedBy: 'recipeItem', targetEntity: ProductionOrderMaterial::class)]
	private Collection $orderMaterials;

	public function __construct()
	{
		$this->quantity = '0.0000';
		$this->orderMaterials = new ArrayCollection();
	}

	public function getId(): ?int { return $this->id; }
	public function getQuantity(): ?string { return $this->quantity; }
	public function setQuantity(string $quantity): self { $this->quantity = $quantity; return $this; }
	public function getWastePercent(): ?string { return $this->wastePercent; }
	public function setWastePercent(?string $wastePercent): self { $this->wastePercent = $wastePercent; return $this; }
	public function getRecipe(): ?ProductionRecipe { return $this->recipe; }
	public function setRecipe(?ProductionRecipe $recipe): self { $this->recipe = $recipe; return $this; }
	public function getMaterial(): ?Product { return $this->material; }
	public function setMaterial(?Product $material): self { $this->material = $material; return $this; }

	/**
	 * @return Collection<int, ProductionOrderMaterial>
	 */
	public function getOrderMaterials(): Collection
	{
		return $this->orderMaterials;
	}
}

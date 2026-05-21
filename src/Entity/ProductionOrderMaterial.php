<?php

namespace App\Entity;

use App\Repository\ProductionOrderMaterialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionOrderMaterialRepository::class)]
#[ORM\Index(name: 'idx_production_order_material_product', columns: ['material_id'])]
class ProductionOrderMaterial
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $plannedQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
	private ?string $wastePercent = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\ManyToOne(inversedBy: 'materials')]
	#[ORM\JoinColumn(nullable: false)]
	private ?ProductionOrder $productionOrder = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $material = null;

	#[ORM\ManyToOne(inversedBy: 'orderMaterials')]
	private ?ProductionRecipeItem $recipeItem = null;

	public function __construct()
	{
		$this->plannedQuantity = '0.0000';
	}

	public function getId(): ?int { return $this->id; }
	public function getPlannedQuantity(): ?string { return $this->plannedQuantity; }
	public function setPlannedQuantity(string $plannedQuantity): self { $this->plannedQuantity = $plannedQuantity; return $this; }
	public function getWastePercent(): ?string { return $this->wastePercent; }
	public function setWastePercent(?string $wastePercent): self { $this->wastePercent = $wastePercent; return $this; }
	public function getComment(): ?string { return $this->comment; }
	public function setComment(?string $comment): self { $this->comment = $comment; return $this; }
	public function getProductionOrder(): ?ProductionOrder { return $this->productionOrder; }
	public function setProductionOrder(?ProductionOrder $productionOrder): self { $this->productionOrder = $productionOrder; return $this; }
	public function getMaterial(): ?Product { return $this->material; }
	public function setMaterial(?Product $material): self { $this->material = $material; return $this; }
	public function getRecipeItem(): ?ProductionRecipeItem { return $this->recipeItem; }
	public function setRecipeItem(?ProductionRecipeItem $recipeItem): self { $this->recipeItem = $recipeItem; return $this; }
}

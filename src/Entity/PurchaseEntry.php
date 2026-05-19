<?php

namespace App\Entity;

use App\Repository\PurchaseEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseEntryRepository::class)]
class PurchaseEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $quantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $receivedQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $returnedQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $unitCost = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $unitCostBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $deliveryCost = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $deliveryCostBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalCost = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalCostBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $salePrice = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $salePriceBase = null;

	#[ORM\Column(length: 255)]
	private ?string $productNameSnapshot = null;

	#[ORM\Column(length: 255)]
	private ?string $productCodeSnapshot = null;

	#[ORM\Column(length: 50)]
	private ?string $unitCodeSnapshot = null;

	#[ORM\Column(length: 255)]
	private ?string $unitNameSnapshot = null;

    #[ORM\ManyToOne(inversedBy: 'purchaseEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $purchase = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Warehouse $warehouse = null;

    /**
     * @var Collection<int, WarehouseStockBatch>
     */
    #[ORM\OneToMany(targetEntity: WarehouseStockBatch::class, mappedBy: 'purchaseEntry')]
    private Collection $warehouseStockBatches;

	/**
	 * @var Collection<int, InventoryDocumentLine>
	 */
	#[ORM\OneToMany(mappedBy: 'purchaseEntry', targetEntity: InventoryDocumentLine::class)]
	private Collection $inventoryDocumentLines;

    public function __construct()
    {
		$this->quantity = '0.0000';
		$this->unitCost = '0.0000';
		$this->unitCostBase = '0.0000';
		$this->totalCost = '0.0000';
		$this->totalCostBase = '0.0000';
        $this->warehouseStockBatches = new ArrayCollection();
		$this->inventoryDocumentLines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): self
    {
        $this->purchase = $purchase;

        return $this;
    }

	public function getQuantity(): ?string { return $this->quantity; }
	public function setQuantity(string $quantity): self { $this->quantity = $quantity; return $this; }
	public function getReceivedQuantity(): ?string { return $this->receivedQuantity; }
	public function setReceivedQuantity(?string $receivedQuantity): self { $this->receivedQuantity = $receivedQuantity; return $this; }
	public function getReturnedQuantity(): ?string { return $this->returnedQuantity; }
	public function setReturnedQuantity(?string $returnedQuantity): self { $this->returnedQuantity = $returnedQuantity; return $this; }
	public function getUnitCost(): ?string { return $this->unitCost; }
	public function setUnitCost(string $unitCost): self { $this->unitCost = $unitCost; return $this; }
	public function getUnitCostBase(): ?string { return $this->unitCostBase; }
	public function setUnitCostBase(string $unitCostBase): self { $this->unitCostBase = $unitCostBase; return $this; }
	public function getDeliveryCost(): ?string { return $this->deliveryCost; }
	public function setDeliveryCost(?string $deliveryCost): self { $this->deliveryCost = $deliveryCost; return $this; }
	public function getDeliveryCostBase(): ?string { return $this->deliveryCostBase; }
	public function setDeliveryCostBase(?string $deliveryCostBase): self { $this->deliveryCostBase = $deliveryCostBase; return $this; }
	public function getTotalCost(): ?string { return $this->totalCost; }
	public function setTotalCost(string $totalCost): self { $this->totalCost = $totalCost; return $this; }
	public function getTotalCostBase(): ?string { return $this->totalCostBase; }
	public function setTotalCostBase(string $totalCostBase): self { $this->totalCostBase = $totalCostBase; return $this; }
	public function getSalePrice(): ?string { return $this->salePrice; }
	public function setSalePrice(?string $salePrice): self { $this->salePrice = $salePrice; return $this; }
	public function getSalePriceBase(): ?string { return $this->salePriceBase; }
	public function setSalePriceBase(?string $salePriceBase): self { $this->salePriceBase = $salePriceBase; return $this; }
	public function getProductNameSnapshot(): ?string { return $this->productNameSnapshot; }
	public function setProductNameSnapshot(string $productNameSnapshot): self { $this->productNameSnapshot = $productNameSnapshot; return $this; }
	public function getProductCodeSnapshot(): ?string { return $this->productCodeSnapshot; }
	public function setProductCodeSnapshot(string $productCodeSnapshot): self { $this->productCodeSnapshot = $productCodeSnapshot; return $this; }
	public function getUnitCodeSnapshot(): ?string { return $this->unitCodeSnapshot; }
	public function setUnitCodeSnapshot(string $unitCodeSnapshot): self { $this->unitCodeSnapshot = $unitCodeSnapshot; return $this; }
	public function getUnitNameSnapshot(): ?string { return $this->unitNameSnapshot; }
	public function setUnitNameSnapshot(string $unitNameSnapshot): self { $this->unitNameSnapshot = $unitNameSnapshot; return $this; }
	public function getProduct(): ?Product { return $this->product; }
	public function setProduct(?Product $product): self { $this->product = $product; return $this; }
	public function getWarehouse(): ?Warehouse { return $this->warehouse; }
	public function setWarehouse(?Warehouse $warehouse): self { $this->warehouse = $warehouse; return $this; }

    /**
     * @return Collection<int, WarehouseStockBatch>
     */
    public function getWarehouseStockBatches(): Collection
    {
        return $this->warehouseStockBatches;
    }

    public function addWarehouseStockBatch(WarehouseStockBatch $warehouseStockBatch): static
    {
        if (!$this->warehouseStockBatches->contains($warehouseStockBatch)) {
            $this->warehouseStockBatches->add($warehouseStockBatch);
            $warehouseStockBatch->setPurchaseEntry($this);
        }

        return $this;
    }

    public function removeWarehouseStockBatch(WarehouseStockBatch $warehouseStockBatch): static
    {
        if ($this->warehouseStockBatches->removeElement($warehouseStockBatch)) {
            // set the owning side to null (unless already changed)
            if ($warehouseStockBatch->getPurchaseEntry() === $this) {
                $warehouseStockBatch->setPurchaseEntry(null);
            }
        }

        return $this;
    }

	/**
	 * @return Collection<int, InventoryDocumentLine>
	 */
	public function getInventoryDocumentLines(): Collection
	{
		return $this->inventoryDocumentLines;
	}

	public function addInventoryDocumentLine(InventoryDocumentLine $inventoryDocumentLine): static
	{
		if (!$this->inventoryDocumentLines->contains($inventoryDocumentLine)) {
			$this->inventoryDocumentLines->add($inventoryDocumentLine);
			$inventoryDocumentLine->setPurchaseEntry($this);
		}

		return $this;
	}

	public function removeInventoryDocumentLine(InventoryDocumentLine $inventoryDocumentLine): static
	{
		if ($this->inventoryDocumentLines->removeElement($inventoryDocumentLine) && $inventoryDocumentLine->getPurchaseEntry() === $this) {
			$inventoryDocumentLine->setPurchaseEntry(null);
		}

		return $this;
	}
}

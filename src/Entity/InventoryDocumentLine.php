<?php

namespace App\Entity;

use App\Enum\InventoryDirection;
use App\Repository\InventoryDocumentLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryDocumentLineRepository::class)]
#[ORM\Index(name: 'idx_inventory_document_line_document', columns: ['inventory_document_id'])]
#[ORM\Index(name: 'idx_inventory_document_line_product_warehouse', columns: ['product_id', 'warehouse_id'])]
class InventoryDocumentLine
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $quantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $unitPrice = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $unitPriceBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $totalPrice = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $totalPriceBase = null;

	#[ORM\Column(length: 255, enumType: InventoryDirection::class)]
	private InventoryDirection $direction;

	#[ORM\ManyToOne(inversedBy: 'lines')]
	#[ORM\JoinColumn(nullable: false)]
	private ?InventoryDocument $inventoryDocument = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Warehouse $warehouse = null;

	#[ORM\ManyToOne(inversedBy: 'inventoryDocumentLines')]
	private ?WarehouseStock $warehouseStock = null;

	#[ORM\ManyToOne]
	private ?OrderEntry $orderEntry = null;

	#[ORM\ManyToOne]
	private ?PurchaseEntry $purchaseEntry = null;

	/**
	 * @var Collection<int, StockMovement>
	 */
	#[ORM\OneToMany(mappedBy: 'inventoryDocumentLine', targetEntity: StockMovement::class)]
	private Collection $stockMovements;

	public function __construct()
	{
		$this->quantity = '0.0000';
		$this->direction = InventoryDirection::IN;
		$this->stockMovements = new ArrayCollection();
	}

	public function getId(): ?int { return $this->id; }
	public function getQuantity(): ?string { return $this->quantity; }
	public function setQuantity(string $quantity): self { $this->quantity = $quantity; return $this; }
	public function getUnitPrice(): ?string { return $this->unitPrice; }
	public function setUnitPrice(?string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }
	public function getUnitPriceBase(): ?string { return $this->unitPriceBase; }
	public function setUnitPriceBase(?string $unitPriceBase): self { $this->unitPriceBase = $unitPriceBase; return $this; }
	public function getTotalPrice(): ?string { return $this->totalPrice; }
	public function setTotalPrice(?string $totalPrice): self { $this->totalPrice = $totalPrice; return $this; }
	public function getTotalPriceBase(): ?string { return $this->totalPriceBase; }
	public function setTotalPriceBase(?string $totalPriceBase): self { $this->totalPriceBase = $totalPriceBase; return $this; }
	public function getDirection(): InventoryDirection { return $this->direction; }
	public function setDirection(InventoryDirection $direction): self { $this->direction = $direction; return $this; }
	public function getInventoryDocument(): ?InventoryDocument { return $this->inventoryDocument; }
	public function setInventoryDocument(?InventoryDocument $inventoryDocument): self { $this->inventoryDocument = $inventoryDocument; return $this; }
	public function getProduct(): ?Product { return $this->product; }
	public function setProduct(?Product $product): self { $this->product = $product; return $this; }
	public function getWarehouse(): ?Warehouse { return $this->warehouse; }
	public function setWarehouse(?Warehouse $warehouse): self { $this->warehouse = $warehouse; return $this; }
	public function getWarehouseStock(): ?WarehouseStock { return $this->warehouseStock; }
	public function setWarehouseStock(?WarehouseStock $warehouseStock): self { $this->warehouseStock = $warehouseStock; return $this; }
	public function getOrderEntry(): ?OrderEntry { return $this->orderEntry; }
	public function setOrderEntry(?OrderEntry $orderEntry): self { $this->orderEntry = $orderEntry; return $this; }
	public function getPurchaseEntry(): ?PurchaseEntry { return $this->purchaseEntry; }
	public function setPurchaseEntry(?PurchaseEntry $purchaseEntry): self { $this->purchaseEntry = $purchaseEntry; return $this; }

	/**
	 * @return Collection<int, StockMovement>
	 */
	public function getStockMovements(): Collection
	{
		return $this->stockMovements;
	}

	public function addStockMovement(StockMovement $stockMovement): self
	{
		if (!$this->stockMovements->contains($stockMovement)) {
			$this->stockMovements->add($stockMovement);
			$stockMovement->setInventoryDocumentLine($this);
		}

		return $this;
	}
}

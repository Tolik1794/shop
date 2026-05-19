<?php

namespace App\Entity;

use App\Repository\StockMovementRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Index(name: 'idx_stock_movement_warehouse_stock_created_at', columns: ['warehouse_stock_id', 'created_at'])]
#[ORM\Index(name: 'idx_stock_movement_line', columns: ['inventory_document_line_id'])]
#[ORM\Index(name: 'idx_stock_movement_batch', columns: ['warehouse_stock_batch_id'])]
class StockMovement
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $quantityChange = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $unitCost = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $balanceAfter = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\ManyToOne(inversedBy: 'stockMovements')]
	#[ORM\JoinColumn(nullable: false)]
	private ?InventoryDocumentLine $inventoryDocumentLine = null;

	#[ORM\ManyToOne(inversedBy: 'stockMovements')]
	#[ORM\JoinColumn(nullable: false)]
	private ?WarehouseStock $warehouseStock = null;

	#[ORM\ManyToOne(inversedBy: 'stockMovements')]
	private ?WarehouseStockBatch $warehouseStockBatch = null;

	public function __construct()
	{
		$this->quantityChange = '0.0000';
		$this->unitCost = '0.0000';
		$this->createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getQuantityChange(): ?string { return $this->quantityChange; }
	public function setQuantityChange(string $quantityChange): self { $this->quantityChange = $quantityChange; return $this; }
	public function getUnitCost(): ?string { return $this->unitCost; }
	public function setUnitCost(string $unitCost): self { $this->unitCost = $unitCost; return $this; }
	public function getBalanceAfter(): ?string { return $this->balanceAfter; }
	public function setBalanceAfter(?string $balanceAfter): self { $this->balanceAfter = $balanceAfter; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getInventoryDocumentLine(): ?InventoryDocumentLine { return $this->inventoryDocumentLine; }
	public function setInventoryDocumentLine(?InventoryDocumentLine $inventoryDocumentLine): self { $this->inventoryDocumentLine = $inventoryDocumentLine; return $this; }
	public function getWarehouseStock(): ?WarehouseStock { return $this->warehouseStock; }
	public function setWarehouseStock(?WarehouseStock $warehouseStock): self { $this->warehouseStock = $warehouseStock; return $this; }
	public function getWarehouseStockBatch(): ?WarehouseStockBatch { return $this->warehouseStockBatch; }
	public function setWarehouseStockBatch(?WarehouseStockBatch $warehouseStockBatch): self { $this->warehouseStockBatch = $warehouseStockBatch; return $this; }
}

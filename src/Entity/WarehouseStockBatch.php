<?php

namespace App\Entity;

use App\Repository\WarehouseStockBatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseStockBatchRepository::class)]
class WarehouseStockBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $initialQuantity = null;

    #[ORM\Column]
    private ?int $remainingQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $salePrice = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStockBatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WarehouseStock $warehouseStock = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStockBatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PurchaseEntry $purchaseEntry = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStockBatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStockBatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Warehouse $warehouse = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInitialQuantity(): ?int
    {
        return $this->initialQuantity;
    }

    public function setInitialQuantity(int $initialQuantity): static
    {
        $this->initialQuantity = $initialQuantity;

        return $this;
    }

    public function getRemainingQuantity(): ?int
    {
        return $this->remainingQuantity;
    }

    public function setRemainingQuantity(int $remainingQuantity): static
    {
        $this->remainingQuantity = $remainingQuantity;

        return $this;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getSalePrice(): ?string
    {
        return $this->salePrice;
    }

    public function setSalePrice(string $salePrice): static
    {
        $this->salePrice = $salePrice;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getWarehouseStock(): ?WarehouseStock
    {
        return $this->warehouseStock;
    }

    public function setWarehouseStock(?WarehouseStock $warehouseStock): static
    {
        $this->warehouseStock = $warehouseStock;

        return $this;
    }

    public function getPurchaseEntry(): ?PurchaseEntry
    {
        return $this->purchaseEntry;
    }

    public function setPurchaseEntry(?PurchaseEntry $purchaseEntry): static
    {
        $this->purchaseEntry = $purchaseEntry;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }
}

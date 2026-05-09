<?php

namespace App\Entity;

use App\Repository\WarehouseStockBatchRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseStockBatchRepository::class)]
class WarehouseStockBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $initialQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $remainingQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $unitCost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
    private ?string $salePrice = null;

    #[ORM\Column]
    private ?DateTimeImmutable $receivedAt = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'warehouseStockBatches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WarehouseStock $warehouseStock = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStockBatches')]
    #[ORM\JoinColumn(nullable: true)]
    private ?PurchaseEntry $purchaseEntry = null;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
	}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInitialQuantity(): ?string
    {
        return $this->initialQuantity;
    }

    public function setInitialQuantity(string $initialQuantity): static
    {
        $this->initialQuantity = $initialQuantity;

        return $this;
    }

    public function getRemainingQuantity(): ?string
    {
        return $this->remainingQuantity;
    }

    public function setRemainingQuantity(string $remainingQuantity): static
    {
        $this->remainingQuantity = $remainingQuantity;

        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(string $unitCost): static
    {
        $this->unitCost = $unitCost;

        return $this;
    }

    public function getSalePrice(): ?string
    {
        return $this->salePrice;
    }

    public function setSalePrice(?string $salePrice): static
    {
        $this->salePrice = $salePrice;

        return $this;
    }

    public function getReceivedAt(): ?DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;

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

}

<?php

namespace App\Entity;

use App\Repository\WarehouseStockRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'warehouse_stock')]
#[ORM\UniqueConstraint(columns: ['product_id', 'warehouse_id'])]
#[ORM\Entity(repositoryClass: WarehouseStockRepository::class)]
class WarehouseStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $quantityOnHand = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $reservedQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $averageCost = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Warehouse $warehouse = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    /**
     * @var Collection<int, WarehouseStockBatch>
     */
    #[ORM\OneToMany(targetEntity: WarehouseStockBatch::class, mappedBy: 'warehouseStock')]
    private Collection $warehouseStockBatches;

    public function __construct()
    {
		$this->quantityOnHand = '0.0000';
		$this->reservedQuantity = '0.0000';
		$this->averageCost = '0.0000';
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
        $this->warehouseStockBatches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantityOnHand(): ?string
    {
        return $this->quantityOnHand;
    }

    public function setQuantityOnHand(string $quantityOnHand): self
    {
        $this->quantityOnHand = $quantityOnHand;

        return $this;
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): self
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getReservedQuantity(): ?string
    {
        return $this->reservedQuantity;
    }

    public function setReservedQuantity(string $reservedQuantity): self
    {
        $this->reservedQuantity = $reservedQuantity;

        return $this;
    }

    public function getAverageCost(): ?string
    {
        return $this->averageCost;
    }

    public function setAverageCost(string $averageCost): self
    {
        $this->averageCost = $averageCost;

        return $this;
    }

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): self
	{
		$this->createdAt = $createdAt;

		return $this;
	}

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTimeImmutable $updatedAt): self
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}

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
            $warehouseStockBatch->setWarehouseStock($this);
        }

        return $this;
    }

    public function removeWarehouseStockBatch(WarehouseStockBatch $warehouseStockBatch): static
    {
        if ($this->warehouseStockBatches->removeElement($warehouseStockBatch)) {
            // set the owning side to null (unless already changed)
            if ($warehouseStockBatch->getWarehouseStock() === $this) {
                $warehouseStockBatch->setWarehouseStock(null);
            }
        }

        return $this;
    }
}

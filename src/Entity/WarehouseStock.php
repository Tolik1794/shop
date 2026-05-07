<?php

namespace App\Entity;

use App\Repository\WarehouseStockRepository;
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
    private ?string $averageCostPrice = null;

    #[ORM\Column]
    private ?int $count = null;

    #[ORM\Column]
    private ?int $reserveCount = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Warehouse $warehouse = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\OneToMany(mappedBy: 'warehouseStock', targetEntity: Entry::class)]
    private Collection $entries;

    /**
     * @var Collection<int, WarehouseStockBatch>
     */
    #[ORM\OneToMany(targetEntity: WarehouseStockBatch::class, mappedBy: 'warehouseStock')]
    private Collection $warehouseStockBatches;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
        $this->warehouseStockBatches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAverageCostPrice(): ?string
    {
        return $this->averageCostPrice;
    }

    public function setAverageCostPrice(string $averageCostPrice): self
    {
        $this->averageCostPrice = $averageCostPrice;

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

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getReserveCount(): ?int
    {
        return $this->reserveCount;
    }

    public function setReserveCount(int $reserveCount): self
    {
        $this->reserveCount = $reserveCount;

        return $this;
    }

    /**
     * @return Collection<int, Entry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(Entry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setWarehouseStock($this);
        }

        return $this;
    }

    public function removeEntry(Entry $entry): self
    {
        if ($this->entries->removeElement($entry)) {
            // set the owning side to null (unless already changed)
            if ($entry->getWarehouseStock() === $this) {
                $entry->setWarehouseStock(null);
            }
        }

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

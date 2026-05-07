<?php

namespace App\Entity;

use App\Repository\WarehouseStockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'warehouse_product')]
#[ORM\UniqueConstraint(columns: ['product_id', 'warehouse_id'])]
#[ORM\Entity(repositoryClass: WarehouseStockRepository::class)]
class WarehouseStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Warehouse $warehouse = null;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $minimumSellingPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $sellingPrice = null;

    #[ORM\Column]
    private ?int $count = null;

    #[ORM\Column]
    private ?int $reserveCount = null;

    #[ORM\OneToMany(mappedBy: 'warehouseStock', targetEntity: Entry::class)]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getMinimumSellingPrice(): ?string
    {
        return $this->minimumSellingPrice;
    }

    public function setMinimumSellingPrice(string $minimumSellingPrice): self
    {
        $this->minimumSellingPrice = $minimumSellingPrice;

        return $this;
    }

    public function getSellingPrice(): ?string
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(string $sellingPrice): self
    {
        $this->sellingPrice = $sellingPrice;

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
}

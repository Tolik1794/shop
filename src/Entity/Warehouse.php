<?php

namespace App\Entity;

use App\Repository\WarehouseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_warehouse_store_name', columns: ['store_id', 'name'])]
class Warehouse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'warehouses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Store $store = null;

    #[ORM\OneToMany(mappedBy: 'warehouse', targetEntity: WarehouseStock::class)]
    private Collection $warehouseStocks;

    /**
     * @var Collection<int, WarehouseStockBatch>
     */
    #[ORM\OneToMany(targetEntity: WarehouseStockBatch::class, mappedBy: 'warehouse')]
    private Collection $warehouseStockBatches;

    public function __construct()
    {
        $this->warehouseStocks = new ArrayCollection();
        $this->warehouseStockBatches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setStore(?Store $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return Collection<int, WarehouseStock>
     */
    public function getWarehouseStocks(): Collection
    {
        return $this->warehouseStocks;
    }

    public function addWarehouseStock(WarehouseStock $warehouseStock): self
    {
        if (!$this->warehouseStocks->contains($warehouseStock)) {
            $this->warehouseStocks->add($warehouseStock);
            $warehouseStock->setWarehouse($this);
        }

        return $this;
    }

    public function removeWarehouseStock(WarehouseStock $warehouseStock): self
    {
        if ($this->warehouseStocks->removeElement($warehouseStock)) {
            // set the owning side to null (unless already changed)
            if ($warehouseStock->getWarehouse() === $this) {
                $warehouseStock->setWarehouse(null);
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
            $warehouseStockBatch->setWarehouse($this);
        }

        return $this;
    }

    public function removeWarehouseStockBatch(WarehouseStockBatch $warehouseStockBatch): static
    {
        if ($this->warehouseStockBatches->removeElement($warehouseStockBatch)) {
            // set the owning side to null (unless already changed)
            if ($warehouseStockBatch->getWarehouse() === $this) {
                $warehouseStockBatch->setWarehouse(null);
            }
        }

        return $this;
    }
}

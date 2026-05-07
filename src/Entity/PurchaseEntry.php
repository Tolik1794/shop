<?php

namespace App\Entity;

use App\Repository\PurchaseEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseEntryRepository::class)]
class PurchaseEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'purchaseEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $purchase = null;

    #[ORM\OneToOne(inversedBy: 'purchaseEntry', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entry $entry = null;

    /**
     * @var Collection<int, WarehouseStockBatch>
     */
    #[ORM\OneToMany(targetEntity: WarehouseStockBatch::class, mappedBy: 'purchaseEntry')]
    private Collection $warehouseStockBatches;

    public function __construct()
    {
        $this->warehouseStockBatches = new ArrayCollection();
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

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(Entry $entry): self
    {
        $this->entry = $entry;

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
}

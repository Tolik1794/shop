<?php

namespace App\Entity;

use App\Repository\PurchaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
class Purchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, enumType: PurchaseStatus::class)]
    private PurchaseStatus $status;

    #[ORM\ManyToOne(inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: false)]
    private Store $store;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseEntry::class)]
    private Collection $purchaseEntries;

    public function __construct()
    {
		$this->status = PurchaseStatus::FORM;
        $this->purchaseEntries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): PurchaseStatus
    {
        return $this->status;
    }

    public function setStatus(PurchaseStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function setStore(Store $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return Collection<int, PurchaseEntry>
     */
    public function getPurchaseEntries(): Collection
    {
        return $this->purchaseEntries;
    }

    public function addPurchaseEntry(PurchaseEntry $purchaseEntry): self
    {
        if (!$this->purchaseEntries->contains($purchaseEntry)) {
            $this->purchaseEntries->add($purchaseEntry);
            $purchaseEntry->setPurchase($this);
        }

        return $this;
    }

    public function removePurchaseEntry(PurchaseEntry $purchaseEntry): self
    {
        if ($this->purchaseEntries->removeElement($purchaseEntry)) {
            // set the owning side to null (unless already changed)
            if ($purchaseEntry->getPurchase() === $this) {
                $purchaseEntry->setPurchase(null);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\EntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryRepository::class)]
class Entry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
    private ?string $cost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $delivery_cost = null;

    #[ORM\OneToOne(mappedBy: 'entry', cascade: ['persist', 'remove'])]
    private ?OrderEntry $orderEntry = null;

    #[ORM\OneToOne(mappedBy: 'entry', cascade: ['persist', 'remove'])]
    private ?PurchaseEntry $purchaseEntry = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(string $cost): self
    {
        $this->cost = $cost;

        return $this;
    }

    public function getDeliveryCost(): ?string
    {
        return $this->delivery_cost;
    }

    public function setDeliveryCost(?string $delivery_cost): self
    {
        $this->delivery_cost = $delivery_cost;

        return $this;
    }

    public function getOrderEntry(): ?OrderEntry
    {
        return $this->orderEntry;
    }

    public function setOrderEntry(OrderEntry $orderEntry): self
    {
        // set the owning side of the relation if necessary
        if ($orderEntry->getEntry() !== $this) {
            $orderEntry->setEntry($this);
        }

        $this->orderEntry = $orderEntry;

        return $this;
    }

    public function getPurchaseEntry(): ?PurchaseEntry
    {
        return $this->purchaseEntry;
    }

    public function setPurchaseEntry(PurchaseEntry $purchaseEntry): self
    {
        // set the owning side of the relation if necessary
        if ($purchaseEntry->getEntry() !== $this) {
            $purchaseEntry->setEntry($this);
        }

        $this->purchaseEntry = $purchaseEntry;

        return $this;
    }

}

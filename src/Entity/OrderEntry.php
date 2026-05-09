<?php

namespace App\Entity;

use App\Repository\OrderEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderEntryRepository::class)]
class OrderEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $quantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $shippedQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $returnedQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $canceledQuantity = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $unitPrice = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $unitPriceBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $discountAmount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $discountAmountBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalPrice = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalPriceBase = null;

	#[ORM\Column(length: 255)]
	private ?string $productNameSnapshot = null;

	#[ORM\Column(length: 255)]
	private ?string $productCodeSnapshot = null;

	#[ORM\Column(length: 50)]
	private ?string $unitCodeSnapshot = null;

	#[ORM\Column(length: 255)]
	private ?string $unitNameSnapshot = null;

    #[ORM\ManyToOne(inversedBy: 'orderEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	#[ORM\ManyToOne]
	private ?Warehouse $warehouse = null;

	public function __construct()
	{
		$this->quantity = '0.0000';
		$this->unitPrice = '0.0000';
		$this->unitPriceBase = '0.0000';
		$this->totalPrice = '0.0000';
		$this->totalPriceBase = '0.0000';
	}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

        return $this;
    }

	public function getQuantity(): ?string { return $this->quantity; }
	public function setQuantity(string $quantity): self { $this->quantity = $quantity; return $this; }
	public function getShippedQuantity(): ?string { return $this->shippedQuantity; }
	public function setShippedQuantity(?string $shippedQuantity): self { $this->shippedQuantity = $shippedQuantity; return $this; }
	public function getReturnedQuantity(): ?string { return $this->returnedQuantity; }
	public function setReturnedQuantity(?string $returnedQuantity): self { $this->returnedQuantity = $returnedQuantity; return $this; }
	public function getCanceledQuantity(): ?string { return $this->canceledQuantity; }
	public function setCanceledQuantity(?string $canceledQuantity): self { $this->canceledQuantity = $canceledQuantity; return $this; }
	public function getUnitPrice(): ?string { return $this->unitPrice; }
	public function setUnitPrice(string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }
	public function getUnitPriceBase(): ?string { return $this->unitPriceBase; }
	public function setUnitPriceBase(string $unitPriceBase): self { $this->unitPriceBase = $unitPriceBase; return $this; }
	public function getDiscountAmount(): ?string { return $this->discountAmount; }
	public function setDiscountAmount(?string $discountAmount): self { $this->discountAmount = $discountAmount; return $this; }
	public function getDiscountAmountBase(): ?string { return $this->discountAmountBase; }
	public function setDiscountAmountBase(?string $discountAmountBase): self { $this->discountAmountBase = $discountAmountBase; return $this; }
	public function getTotalPrice(): ?string { return $this->totalPrice; }
	public function setTotalPrice(string $totalPrice): self { $this->totalPrice = $totalPrice; return $this; }
	public function getTotalPriceBase(): ?string { return $this->totalPriceBase; }
	public function setTotalPriceBase(string $totalPriceBase): self { $this->totalPriceBase = $totalPriceBase; return $this; }
	public function getProductNameSnapshot(): ?string { return $this->productNameSnapshot; }
	public function setProductNameSnapshot(string $productNameSnapshot): self { $this->productNameSnapshot = $productNameSnapshot; return $this; }
	public function getProductCodeSnapshot(): ?string { return $this->productCodeSnapshot; }
	public function setProductCodeSnapshot(string $productCodeSnapshot): self { $this->productCodeSnapshot = $productCodeSnapshot; return $this; }
	public function getUnitCodeSnapshot(): ?string { return $this->unitCodeSnapshot; }
	public function setUnitCodeSnapshot(string $unitCodeSnapshot): self { $this->unitCodeSnapshot = $unitCodeSnapshot; return $this; }
	public function getUnitNameSnapshot(): ?string { return $this->unitNameSnapshot; }
	public function setUnitNameSnapshot(string $unitNameSnapshot): self { $this->unitNameSnapshot = $unitNameSnapshot; return $this; }
	public function getProduct(): ?Product { return $this->product; }
	public function setProduct(?Product $product): self { $this->product = $product; return $this; }
	public function getWarehouse(): ?Warehouse { return $this->warehouse; }
	public function setWarehouse(?Warehouse $warehouse): self { $this->warehouse = $warehouse; return $this; }
}

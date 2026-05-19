<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ProductPriceTypeEnum;
use App\Repository\ProductPriceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductPriceRepository::class)]
class ProductPrice
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255, enumType: ProductPriceTypeEnum::class)]
	private ProductPriceTypeEnum $type;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $price = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $priceBase = null;

	#[ORM\Column]
	private DateTimeImmutable $validFrom;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $validTo = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\ManyToOne(inversedBy: 'productPrices')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	#[ORM\ManyToOne(inversedBy: 'productPrices')]
	#[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $currency = null;

	#[ORM\ManyToOne(inversedBy: 'productPrices')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	public function __construct()
	{
		$this->type = ProductPriceTypeEnum::REGULAR;
		$this->validFrom = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getType(): ProductPriceTypeEnum
	{
		return $this->type;
	}

	public function setType(ProductPriceTypeEnum $type): self
	{
		$this->type = $type;

		return $this;
	}

	public function getPrice(): ?string
	{
		return $this->price;
	}

	public function setPrice(string $price): self
	{
		$this->price = $price;

		return $this;
	}

	public function getPriceBase(): ?string
	{
		return $this->priceBase;
	}

	public function setPriceBase(string $priceBase): self
	{
		$this->priceBase = $priceBase;

		return $this;
	}

	public function getValidFrom(): DateTimeImmutable
	{
		return $this->validFrom;
	}

	public function setValidFrom(DateTimeImmutable $validFrom): self
	{
		$this->validFrom = $validFrom;

		return $this;
	}

	public function getValidTo(): ?DateTimeImmutable
	{
		return $this->validTo;
	}

	public function setValidTo(?DateTimeImmutable $validTo): self
	{
		$this->validTo = $validTo;

		return $this;
	}

	public function getComment(): ?string
	{
		return $this->comment;
	}

	public function setComment(?string $comment): self
	{
		$this->comment = $comment;

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

	public function getCurrency(): ?Currency
	{
		return $this->currency;
	}

	public function setCurrency(?Currency $currency): self
	{
		$this->currency = $currency;

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

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $createdBy): self
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getUpdatedBy(): ?User
	{
		return $this->updatedBy;
	}

	public function setUpdatedBy(?User $updatedBy): self
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}
}

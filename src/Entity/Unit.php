<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Repository\UnitRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnitRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_unit_store_code', columns: ['store_id', 'code'])]
class Unit
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 50)]
	private ?string $code = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column]
	private ?int $precision = null;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

	#[ORM\ManyToOne(inversedBy: 'units')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\OneToMany(mappedBy: 'unit', targetEntity: Product::class)]
	private Collection $products;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->products = new ArrayCollection();
	}

	public function __toString(): string
	{
		return (string)$this->name;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getCode(): ?string
	{
		return $this->code;
	}

	public function setCode(string $code): self
	{
		$this->code = $code;

		return $this;
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

	public function getPrecision(): ?int
	{
		return $this->precision;
	}

	public function setPrecision(int $precision): self
	{
		$this->precision = $precision;

		return $this;
	}

	public function getStatus(): ActiveStatusEnum
	{
		return $this->status;
	}

	public function setStatus(ActiveStatusEnum $status): self
	{
		$this->status = $status;

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

	public function getDeletedAt(): ?DateTimeImmutable
	{
		return $this->deletedAt;
	}

	public function setDeletedAt(?DateTimeImmutable $deletedAt): self
	{
		$this->deletedAt = $deletedAt;

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

	/**
	 * @return Collection<int, Product>
	 */
	public function getProducts(): Collection
	{
		return $this->products;
	}

	public function addProduct(Product $product): self
	{
		if (!$this->products->contains($product)) {
			$this->products->add($product);
			$product->setUnit($this);
		}

		return $this;
	}

	public function removeProduct(Product $product): self
	{
		if ($this->products->removeElement($product) && $product->getUnit() === $this) {
			$product->setUnit(null);
		}

		return $this;
	}
}

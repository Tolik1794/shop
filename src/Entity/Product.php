<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Enum\ProductKindEnum;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_product_store_code', columns: ['store_id', 'code'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

	#[ORM\Column]
	private ?bool $canBeSold = null;

	#[ORM\Column]
	private ?bool $canBePurchased = null;

	#[ORM\Column]
	private ?bool $canBeManufactured = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $baseSalePrice = null;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Store $store = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Unit $unit = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\Column(length: 255, enumType: ProductKindEnum::class)]
	private ProductKindEnum $productKind;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: WarehouseStock::class)]
    private Collection $warehouseStocks;

	#[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductPrice::class)]
	private Collection $productPrices;

    #[ORM\OneToMany(
		targetEntity: ProductParameter::class,
	    mappedBy: 'product',
	    cascade: ['persist'],
	    orphanRemoval: true
    )]
    private Collection $productParameters;

    #[ORM\OneToMany(
		targetEntity: ProductRelation::class,
	    mappedBy: 'product',
	    cascade: ['persist'],
	    orphanRemoval: true
    )]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $productRelations;

    public function __construct()
    {
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
        $this->productKind = ProductKindEnum::FINISHED_PRODUCT;
        $this->warehouseStocks = new ArrayCollection();
		$this->productPrices = new ArrayCollection();
        $this->productParameters = new ArrayCollection();
        $this->productRelations = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function isCanBeSold(): ?bool
    {
        return $this->canBeSold;
    }

    public function setCanBeSold(bool $canBeSold): static
    {
        $this->canBeSold = $canBeSold;

        return $this;
    }

    public function isCanBePurchased(): ?bool
    {
        return $this->canBePurchased;
    }

    public function setCanBePurchased(bool $canBePurchased): static
    {
        $this->canBePurchased = $canBePurchased;

        return $this;
    }

    public function isCanBeManufactured(): ?bool
    {
        return $this->canBeManufactured;
    }

    public function setCanBeManufactured(bool $canBeManufactured): static
    {
        $this->canBeManufactured = $canBeManufactured;

        return $this;
    }

	public function getBaseSalePrice(): ?string
	{
		return $this->baseSalePrice;
	}

	public function setBaseSalePrice(?string $baseSalePrice): static
	{
		$this->baseSalePrice = $baseSalePrice;

		return $this;
	}

	public function getStatus(): ActiveStatusEnum
	{
		return $this->status;
	}

	public function setStatus(ActiveStatusEnum $status): static
	{
		$this->status = $status;

		return $this;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;

		return $this;
	}

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTimeImmutable $updatedAt): static
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}

	public function getDeletedAt(): ?DateTimeImmutable
	{
		return $this->deletedAt;
	}

	public function setDeletedAt(?DateTimeImmutable $deletedAt): static
	{
		$this->deletedAt = $deletedAt;

		return $this;
	}

	public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

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

	public function getUnit(): ?Unit
	{
		return $this->unit;
	}

	public function setUnit(?Unit $unit): static
	{
		$this->unit = $unit;

		return $this;
	}

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $createdBy): static
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getUpdatedBy(): ?User
	{
		return $this->updatedBy;
	}

	public function setUpdatedBy(?User $updatedBy): static
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}

	public function getProductKind(): ProductKindEnum
	{
		return $this->productKind;
	}

	public function setProductKind(ProductKindEnum $productKind): static
	{
		$this->productKind = $productKind;

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
            $warehouseStock->setProduct($this);
        }

        return $this;
    }

    public function removeWarehouseStock(WarehouseStock $warehouseStock): self
    {
        if ($this->warehouseStocks->removeElement($warehouseStock)) {
            // set the owning side to null (unless already changed)
            if ($warehouseStock->getProduct() === $this) {
                $warehouseStock->setProduct(null);
            }
        }

        return $this;
    }

	/**
	 * @return Collection<int, ProductPrice>
	 */
	public function getProductPrices(): Collection
	{
		return $this->productPrices;
	}

	public function addProductPrice(ProductPrice $productPrice): self
	{
		if (!$this->productPrices->contains($productPrice)) {
			$this->productPrices->add($productPrice);
			$productPrice->setProduct($this);
		}

		return $this;
	}

	public function removeProductPrice(ProductPrice $productPrice): self
	{
		if ($this->productPrices->removeElement($productPrice) && $productPrice->getProduct() === $this) {
			$productPrice->setProduct(null);
		}

		return $this;
	}

    /**
     * @return Collection<int, ProductParameter>
     */
    public function getProductParameters(): Collection
    {
        return $this->productParameters;
    }

    public function addProductParameter(ProductParameter $productParameter): self
    {
        if (!$this->productParameters->contains($productParameter)) {
            $this->productParameters->add($productParameter);
            $productParameter->setProduct($this);
        }

        return $this;
    }

    public function removeProductParameter(ProductParameter $productParameter): self
    {
        if ($this->productParameters->removeElement($productParameter)) {
            // set the owning side to null (unless already changed)
            if ($productParameter->getProduct() === $this) {
                $productParameter->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductRelation>
     */
    public function getProductRelations(): Collection
    {
        return $this->productRelations;
    }

    public function addProductRelation(ProductRelation $productRelation): self
    {
        if (!$this->productRelations->contains($productRelation)) {
            $this->productRelations->add($productRelation);
            $productRelation->setProduct($this);
        }

        return $this;
    }

    public function removeProductRelation(ProductRelation $productRelation): self
    {
        if ($this->productRelations->removeElement($productRelation)) {
            // set the owning side to null (unless already changed)
            if ($productRelation->getProduct() === $this) {
                $productRelation->setProduct(null);
            }
        }

        return $this;
    }

}

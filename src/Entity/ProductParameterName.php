<?php

namespace App\Entity;

use App\Repository\ProductParameterNameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductParameterNameRepository::class)]
class ProductParameterName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'name', targetEntity: ProductParameter::class, orphanRemoval: true)]
    private Collection $productParameters;

    #[ORM\OneToMany(mappedBy: 'productParameterName', targetEntity: CategoryProductParameterName::class)]
    private Collection $categoryProductParameterNames;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    public function __construct()
    {
        $this->productParameters = new ArrayCollection();
        $this->categoryProductParameterNames = new ArrayCollection();
    }

	public function __toString(): string
	{
		return $this->getName();
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
            $productParameter->setProductParameterName($this);
        }

        return $this;
    }

    public function removeProductParameter(ProductParameter $productParameter): self
    {
        if ($this->productParameters->removeElement($productParameter)) {
            // set the owning side to null (unless already changed)
            if ($productParameter->getProductParameterName() === $this) {
                $productParameter->setProductParameterName(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CategoryProductParameterName>
     */
    public function getCategoryProductParameterNames(): Collection
    {
        return $this->categoryProductParameterNames;
    }

    public function addCategoryProductParameterName(CategoryProductParameterName $categoryProductParameterName): self
    {
        if (!$this->categoryProductParameterNames->contains($categoryProductParameterName)) {
            $this->categoryProductParameterNames->add($categoryProductParameterName);
            $categoryProductParameterName->setProductParameterName($this);
        }

        return $this;
    }

    public function removeCategoryProductParameterName(CategoryProductParameterName $categoryProductParameterName): self
    {
        if ($this->categoryProductParameterNames->removeElement($categoryProductParameterName)) {
            // set the owning side to null (unless already changed)
            if ($categoryProductParameterName->getProductParameterName() === $this) {
                $categoryProductParameterName->setProductParameterName(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}

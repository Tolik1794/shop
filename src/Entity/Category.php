<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Product::class)]
    private Collection $products;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryAdditionalName::class)]
    private Collection $categoryAdditionalNames;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->categoryAdditionalNames = new ArrayCollection();
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
            $product->setCategory($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getCategory() === $this) {
                $product->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CategoryAdditionalName>
     */
    public function getCategoryAdditionalNames(): Collection
    {
        return $this->categoryAdditionalNames;
    }

    public function addCategoryAdditionalName(CategoryAdditionalName $categoryAdditionalName): self
    {
        if (!$this->categoryAdditionalNames->contains($categoryAdditionalName)) {
            $this->categoryAdditionalNames->add($categoryAdditionalName);
            $categoryAdditionalName->setCategory($this);
        }

        return $this;
    }

    public function removeCategoryAdditionalName(CategoryAdditionalName $categoryAdditionalName): self
    {
        if ($this->categoryAdditionalNames->removeElement($categoryAdditionalName)) {
            // set the owning side to null (unless already changed)
            if ($categoryAdditionalName->getCategory() === $this) {
                $categoryAdditionalName->setCategory(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}

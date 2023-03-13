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

	#[ORM\ManyToOne(inversedBy: 'categories')]
	private ?Store $store = null;

	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	private ?self $parent = null;

	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	private Collection $children;

	#[ORM\ManyToOne(targetEntity: self::class)]
	private ?self $firstParent = null;

	#[ORM\Column]
	private int $level = 0;

	#[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryProductParameterName::class)]
	private Collection $categoryProductParameterNames;

	public function __construct()
	{
		$this->products = new ArrayCollection();
		$this->categoryAdditionalNames = new ArrayCollection();
		$this->children = new ArrayCollection();
		$this->categoryProductParameterNames = new ArrayCollection();
	}

	public function __toString(): string
	{
		return $this->getNameWithParent();
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

	public function getStore(): ?Store
	{
		return $this->store;
	}

	public function setStore(?Store $store): self
	{
		$this->store = $store;

		return $this;
	}

	public function getParent(): ?self
	{
		return $this->parent;
	}

	public function setParent(?self $parent): self
	{
		$this->parent = $parent;

		return $this;
	}

	/**
	 * @return Collection<int, self>
	 */
	public function getChildren(): Collection
	{
		return $this->children;
	}

	public function addChild(self $child): self
	{
		if (!$this->children->contains($child)) {
			$this->children->add($child);
			$child->setParent($this);
		}

		return $this;
	}

	public function removeChild(self $child): self
	{
		if ($this->children->removeElement($child)) {
			// set the owning side to null (unless already changed)
			if ($child->getParent() === $this) {
				$child->setParent(null);
			}
		}

		return $this;
	}

	public function getNameWithParent(): string
	{
		$parentName = $this->getParent()?->getNameWithParent();

		if (!$parentName) return $this->name;

		return sprintf('%s > %s', $parentName, $this->name);
	}

	public function getFirstParent(): ?self
	{
		return $this->firstParent;
	}

	public function setFirstParent(?self $firstParent): self
	{
		$this->firstParent = $firstParent;

		return $this;
	}

	public function getLevel(): int
	{
		return $this->level;
	}

	public function setLevel(int $level): self
	{
		$this->level = $level;

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
			$categoryProductParameterName->setCategory($this);
		}

		return $this;
	}

	public function removeCategoryProductParameterName(CategoryProductParameterName $categoryProductParameterName): self
	{
		if ($this->categoryProductParameterNames->removeElement($categoryProductParameterName)) {
			// set the owning side to null (unless already changed)
			if ($categoryProductParameterName->getCategory() === $this) {
				$categoryProductParameterName->setCategory(null);
			}
		}

		return $this;
	}
}

<?php

namespace App\Entity;

use App\Repository\CategoryProductParameterNameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(columns: ['category_id', 'product_parameter_name_id'])]
#[ORM\Entity(repositoryClass: CategoryProductParameterNameRepository::class)]
class CategoryProductParameterName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'categoryProductParameterNames')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductParameterName $productParameterName = null;

    #[ORM\ManyToOne(inversedBy: 'categoryProductParameterNames')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column]
    private ?bool $isRequired = null;

    #[ORM\Column]
    private ?bool $isFilter = null;

	public function __toString(): string
	{
		return $this->productParameterName->getName();
	}

	public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductParameterName(): ?ProductParameterName
    {
        return $this->productParameterName;
    }

    public function setProductParameterName(?ProductParameterName $productParameterName): self
    {
        $this->productParameterName = $productParameterName;

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

    public function isIsRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    public function isIsFilter(): ?bool
    {
        return $this->isFilter;
    }

    public function setIsFilter(bool $isFilter): self
    {
        $this->isFilter = $isFilter;

        return $this;
    }
}

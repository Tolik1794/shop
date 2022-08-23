<?php

namespace App\Entity;

use App\Repository\CategoryAdditionalNameRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CategoryAdditionalNameRepository::class)]
#[ORM\UniqueConstraint(columns: ['store_id', 'additional_name_id', 'category_id'])]
#[UniqueEntity(
	fields: ['store', 'category', 'additionalName'],
	message: 'There is already an name for this category.'
)]
class CategoryAdditionalName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Store $store = null;

    #[ORM\ManyToOne(inversedBy: 'categoryAdditionalNames')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'categoryAdditionalNames')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdditionalName $additionalName = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getAdditionalName(): ?AdditionalName
    {
        return $this->additionalName;
    }

    public function setAdditionalName(?AdditionalName $additionalName): self
    {
        $this->additionalName = $additionalName;

        return $this;
    }
}

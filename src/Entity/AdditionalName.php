<?php

namespace App\Entity;

use App\Repository\AdditionalNameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdditionalNameRepository::class)]
class AdditionalName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'additionalName', targetEntity: CategoryAdditionalName::class)]
    private Collection $categoryAdditionalNames;

    public function __construct()
    {
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
            $categoryAdditionalName->setAdditionalName($this);
        }

        return $this;
    }

    public function removeCategoryAdditionalName(CategoryAdditionalName $categoryAdditionalName): self
    {
        if ($this->categoryAdditionalNames->removeElement($categoryAdditionalName)) {
            // set the owning side to null (unless already changed)
            if ($categoryAdditionalName->getAdditionalName() === $this) {
                $categoryAdditionalName->setAdditionalName(null);
            }
        }

        return $this;
    }
}

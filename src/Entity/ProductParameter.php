<?php

namespace App\Entity;

use App\Repository\ProductParameterRepository;
use Doctrine\ORM\Mapping as ORM;
#[ORM\UniqueConstraint(columns: ['product_id', 'product_parameter_name_id'])]
#[ORM\Entity(repositoryClass: ProductParameterRepository::class)]
class ProductParameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productParameters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductParameterName $productParameterName = null;

    #[ORM\ManyToOne(inversedBy: 'productParameters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private ?string $value = null;

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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }
}

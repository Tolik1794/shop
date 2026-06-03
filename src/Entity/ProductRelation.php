<?php

namespace App\Entity;

use App\Enum\ProductRelationTypeEnum;
use App\Repository\ProductRelationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRelationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_product_relation', columns: ['product_id', 'related_product_id'])]
class ProductRelation
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne(inversedBy: 'productRelations')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private ?Product $product = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private ?Product $relatedProduct = null;

	#[ORM\Column(length: 32, enumType: ProductRelationTypeEnum::class)]
	private ProductRelationTypeEnum $type = ProductRelationTypeEnum::RELATED;

	#[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
	private int $sortOrder = 0;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getProduct(): ?Product
	{
		return $this->product;
	}

	public function setProduct(?Product $product): static
	{
		$this->product = $product;

		return $this;
	}

	public function getRelatedProduct(): ?Product
	{
		return $this->relatedProduct;
	}

	public function setRelatedProduct(?Product $relatedProduct): static
	{
		$this->relatedProduct = $relatedProduct;

		return $this;
	}

	public function getType(): ProductRelationTypeEnum
	{
		return $this->type;
	}

	public function setType(ProductRelationTypeEnum $type): static
	{
		$this->type = $type;

		return $this;
	}

	public function getSortOrder(): int
	{
		return $this->sortOrder;
	}

	public function setSortOrder(int $sortOrder): static
	{
		$this->sortOrder = $sortOrder;

		return $this;
	}
}

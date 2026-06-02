<?php

namespace App\Entity;

use App\Enum\ProductDiscountTargetTypeEnum;
use App\Repository\ProductDiscountTargetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductDiscountTargetRepository::class)]
#[ORM\Table(name: 'product_discount_target')]
#[ORM\Index(name: 'idx_product_discount_target_rule', columns: ['rule_id'])]
#[ORM\Index(name: 'idx_product_discount_target_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_product_discount_target_category', columns: ['category_id'])]
class ProductDiscountTarget
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 32, enumType: ProductDiscountTargetTypeEnum::class)]
	private ProductDiscountTargetTypeEnum $targetType;

	#[ORM\Column]
	private bool $includeDescendants = true;

	#[ORM\ManyToOne(inversedBy: 'targets')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private ?ProductDiscountRule $rule = null;

	#[ORM\ManyToOne]
	private ?Product $product = null;

	#[ORM\ManyToOne]
	private ?Category $category = null;

	public function __construct()
	{
		$this->targetType = ProductDiscountTargetTypeEnum::PRODUCT;
	}

	public function getId(): ?int { return $this->id; }
	public function getTargetType(): ProductDiscountTargetTypeEnum { return $this->targetType; }
	public function setTargetType(ProductDiscountTargetTypeEnum $targetType): self { $this->targetType = $targetType; return $this; }
	public function isIncludeDescendants(): bool { return $this->includeDescendants; }
	public function setIncludeDescendants(bool $includeDescendants): self { $this->includeDescendants = $includeDescendants; return $this; }
	public function getRule(): ?ProductDiscountRule { return $this->rule; }
	public function setRule(?ProductDiscountRule $rule): self { $this->rule = $rule; return $this; }
	public function getProduct(): ?Product { return $this->product; }
	public function setProduct(?Product $product): self { $this->product = $product; return $this; }
	public function getCategory(): ?Category { return $this->category; }
	public function setCategory(?Category $category): self { $this->category = $category; return $this; }
}

<?php

namespace App\Service\Discount;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\ProductDiscountTarget;
use App\Entity\Store;
use App\Enum\ProductDiscountTargetTypeEnum;
use App\Repository\CategoryRepository;
use App\Repository\ProductDiscountRuleRepository;

class ProductDiscountResolver
{
	public function __construct(
		private readonly ProductDiscountRuleRepository $productDiscountRuleRepository,
		private readonly CategoryRepository $categoryRepository,
	)
	{
	}

	/**
	 * @return ProductDiscountRule[]
	 */
	public function allowedRules(Product $product, Store $store): array
	{
		$matches = [];

		foreach ($this->productDiscountRuleRepository->findActiveCandidatesForProduct($product, $store) as $rule) {
			if (!$this->matchScore($rule, $product)) {
				continue;
			}

			$matches[] = $rule;
		}

		usort($matches, fn (ProductDiscountRule $left, ProductDiscountRule $right): int => $this->compareRules($left, $right, $product));

		return $matches;
	}

	public function defaultRule(Product $product, Store $store): ?ProductDiscountRule
	{
		$rules = array_filter(
			$this->allowedRules($product, $store),
			static fn (ProductDiscountRule $rule): bool => $rule->isDefault(),
		);

		return array_values($rules)[0] ?? null;
	}

	public function isAllowed(ProductDiscountRule $rule, Product $product, Store $store): bool
	{
		if ($rule->getStore()?->getId() !== $store->getId()) {
			return false;
		}

		return $this->matchScore($rule, $product) !== null;
	}

	private function compareRules(ProductDiscountRule $left, ProductDiscountRule $right, Product $product): int
	{
		return [$this->matchScore($right, $product) ?? 0, (float) $right->getPercent(), $right->getId() ?? 0]
			<=> [$this->matchScore($left, $product) ?? 0, (float) $left->getPercent(), $left->getId() ?? 0];
	}

	private function matchScore(ProductDiscountRule $rule, Product $product): ?int
	{
		$bestScore = null;

		foreach ($rule->getTargets() as $target) {
			if (!$target instanceof ProductDiscountTarget) {
				continue;
			}

			$score = $this->targetScore($target, $product);
			if ($score === null) {
				continue;
			}

			$bestScore = max($bestScore ?? $score, $score);
		}

		return $bestScore;
	}

	private function targetScore(ProductDiscountTarget $target, Product $product): ?int
	{
		if ($target->getTargetType() === ProductDiscountTargetTypeEnum::PRODUCT) {
			return $target->getProduct()?->getId() === $product->getId() ? 10000 : null;
		}

		$targetCategory = $target->getCategory();
		$productCategory = $product->getCategory();

		if (!$targetCategory instanceof Category || !$productCategory instanceof Category) {
			return null;
		}

		if ($targetCategory->getId() === $productCategory->getId()) {
			return 9000;
		}

		if (!$target->isIncludeDescendants()) {
			return null;
		}

		$parentIds = array_map('intval', $this->categoryRepository->findAllParentIdRecursive($productCategory));
		$depthIndex = array_search($targetCategory->getId(), $parentIds, true);

		return $depthIndex === false ? null : 8000 - (int) $depthIndex;
	}
}

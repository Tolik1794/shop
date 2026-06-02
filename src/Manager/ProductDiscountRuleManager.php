<?php

namespace App\Manager;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\ProductDiscountTarget;
use App\Entity\Store;
use App\Entity\User\User;
use App\Enum\ProductDiscountTargetTypeEnum;
use App\Repository\ProductDiscountRuleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class ProductDiscountRuleManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly UserManager $userManager,
	)
	{
	}

	public function getRepository(): ProductDiscountRuleRepository
	{
		return $this->entityManager->getRepository(ProductDiscountRule::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	public function create(Store $store): ProductDiscountRule
	{
		return (new ProductDiscountRule())
			->setStore($store);
	}

	/**
	 * @param iterable<ProductDiscountTarget> $removedTargets
	 */
	public function saveRule(ProductDiscountRule $rule, iterable $removedTargets = []): void
	{
		$this->validate($rule);

		$actor = $this->userManager->getCurrentUser();
		if ($actor instanceof User) {
			if ($rule->getId() === null) {
				$rule->setCreatedBy($actor);
			}

			$rule->setUpdatedBy($actor);
		}

		$rule->setUpdatedAt(new DateTimeImmutable());

		foreach ($removedTargets as $removedTarget) {
			if ($removedTarget instanceof ProductDiscountTarget) {
				$this->entityManager->remove($removedTarget);
			}
		}

		foreach ($rule->getTargets() as $target) {
			$target->setRule($rule);
			$this->entityManager->persist($target);
		}

		$this->entityManager->persist($rule);
		$this->entityManager->flush();
	}

	private function validate(ProductDiscountRule $rule): void
	{
		$store = $rule->getStore();
		if (!$store instanceof Store) {
			throw new RuntimeException('Discount rule store is required.');
		}

		$percent = (float) str_replace(',', '.', (string) $rule->getPercent());
		if ($percent <= 0 || $percent > 100) {
			throw new RuntimeException('Discount percent must be greater than zero and not exceed 100.');
		}

		if ($rule->getTargets()->count() === 0) {
			throw new RuntimeException('Discount rule must have at least one product or category target.');
		}

		foreach ($rule->getTargets() as $target) {
			$this->validateTarget($target, $store);
		}
	}

	private function validateTarget(ProductDiscountTarget $target, Store $store): void
	{
		if ($target->getTargetType() === ProductDiscountTargetTypeEnum::PRODUCT) {
			$product = $target->getProduct();
			if (!$product instanceof Product || $product->getStore()?->getId() !== $store->getId()) {
				throw new RuntimeException('Discount product target must belong to the selected store.');
			}

			$target->setCategory(null);

			return;
		}

		$category = $target->getCategory();
		if (!$category instanceof Category || $category->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Discount category target must belong to the selected store.');
		}

		$target->setProduct(null);
	}
}

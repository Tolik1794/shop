<?php

namespace App\Manager;

use App\Entity\Product;
use App\Entity\ProductRelation;
use App\Repository\ProductRelationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps product relations mirrored (bidirectional). When a product A declares a
 * relation to product B, an inverse relation B -> A with the same type is kept in
 * sync; removing one side removes the mirror as well. Relations are always
 * maintained in pairs, so an inverse row without its direct counterpart is treated
 * as an orphaned mirror and removed.
 */
class ProductRelationManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ProductRelationRepository $productRelationRepository,
	)
	{
	}

	/**
	 * Synchronise the inverse relations for the given product. Must be called after
	 * the product (and its outgoing relations) has been persisted/flushed.
	 */
	public function syncMirrors(Product $product): void
	{
		$this->entityManager->wrapInTransaction(function () use ($product): void {
			$currentTargetIds = [];

			foreach ($product->getProductRelations() as $relation) {
				$relatedProduct = $relation->getRelatedProduct();

				if (!$relatedProduct instanceof Product || $relatedProduct === $product) {
					continue;
				}

				if ($relatedProduct->getStore()?->getId() !== $product->getStore()?->getId()) {
					continue;
				}

				$currentTargetIds[$relatedProduct->getId()] = true;

				$mirror = $this->productRelationRepository->findOnePair($relatedProduct, $product);

				if ($mirror instanceof ProductRelation) {
					$mirror->setType($relation->getType());

					continue;
				}

				$mirror = (new ProductRelation())
					->setProduct($relatedProduct)
					->setRelatedProduct($product)
					->setType($relation->getType());
				$this->entityManager->persist($mirror);
			}

			// Drop orphaned mirrors that point back to this product but no longer have a direct counterpart.
			foreach ($this->productRelationRepository->findByRelatedProduct($product) as $inverseRelation) {
				$owner = $inverseRelation->getProduct();

				if (!$owner instanceof Product || $owner === $product) {
					continue;
				}

				if (!isset($currentTargetIds[$owner->getId()])) {
					$this->entityManager->remove($inverseRelation);
				}
			}

			$this->entityManager->flush();
		});
	}
}

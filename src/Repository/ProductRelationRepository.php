<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductRelation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductRelation>
 *
 * @method ProductRelation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductRelation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductRelation[]    findAll()
 * @method ProductRelation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRelationRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductRelation::class);
	}

	public function findOnePair(Product $product, Product $relatedProduct): ?ProductRelation
	{
		return $this->findOneBy([
			'product' => $product,
			'relatedProduct' => $relatedProduct,
		]);
	}

	/**
	 * @return ProductRelation[]
	 */
	public function findByRelatedProduct(Product $relatedProduct): array
	{
		return $this->findBy(['relatedProduct' => $relatedProduct]);
	}
}

<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductionRecipe;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductionRecipe>
 *
 * @method ProductionRecipe|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionRecipe|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionRecipe[]    findAll()
 * @method ProductionRecipe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionRecipeRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductionRecipe::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('productionRecipe')
			->innerJoin('productionRecipe.product', 'product')
			->addSelect('product')
			->leftJoin('productionRecipe.items', 'item')
			->addSelect('item')
			->leftJoin('item.material', 'material')
			->addSelect('material')
			->andWhere('productionRecipe.store = :store')
			->setParameter('store', $store);
	}

	public function findDefaultForProduct(Product $product): ?ProductionRecipe
	{
		return $this->findOneBy([
			'product' => $product,
			'store' => $product->getStore(),
			'isDefault' => true,
		]);
	}

	public function getIndexPage(ProductionRecipe $productionRecipe, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('productionRecipe')
			->select('COUNT(productionRecipe.id)')
			->andWhere('productionRecipe.store = :store')
			->andWhere('productionRecipe.id >= :id')
			->setParameter('store', $productionRecipe->getStore())
			->setParameter('id', $productionRecipe->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}
}

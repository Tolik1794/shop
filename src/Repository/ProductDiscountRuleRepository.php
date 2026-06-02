<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductDiscountRule>
 */
class ProductDiscountRuleRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductDiscountRule::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('rule')
			->andWhere('rule.store = :store')
			->setParameter('store', $store);
	}

	public function getIndexPage(ProductDiscountRule $rule, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('rule')
			->select('COUNT(rule.id)')
			->andWhere('rule.store = :store')
			->andWhere('rule.id <= :id')
			->setParameter('store', $rule->getStore())
			->setParameter('id', $rule->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}

	/**
	 * @return ProductDiscountRule[]
	 */
	public function findActiveCandidatesForProduct(Product $product, Store $store): array
	{
		return $this->createQueryBuilder('rule')
			->leftJoin('rule.targets', 'target')
			->addSelect('target')
			->leftJoin('target.product', 'targetProduct')
			->addSelect('targetProduct')
			->leftJoin('target.category', 'targetCategory')
			->addSelect('targetCategory')
			->andWhere('rule.store = :store')
			->andWhere('rule.status = :status')
			->setParameter('store', $store)
			->setParameter('status', ActiveStatusEnum::ACTIVE)
			->orderBy('rule.id', 'DESC')
			->getQuery()
			->getResult();
	}
}

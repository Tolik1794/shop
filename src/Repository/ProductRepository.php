<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductParameter;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Enum\ProductKindEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function add(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('product')
			->innerJoin('product.store', 'store')
			->where('store = :store')
			->setParameter('store', $store);
	}

	public function getIndexPage(Product $product, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('product')
			->select('COUNT(product.id)')
			->andWhere('product.store = :store')
			->andWhere('product.id <= :id')
			->setParameter('store', $product->getStore())
			->setParameter('id', $product->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}

	/**
	 * Returns active, non-deleted products for the given store that match every token of
	 * the raw search string in at least one of: name, code (SKU), category name, or parameter value.
	 * Result cap defaults to 500 — callers are expected to score and rank results in PHP.
	 *
	 * @return Product[]
	 */
	public function findForOrderEntrySearch(Store $store, string $rawSearch, int $limit = 500): array
	{
		$rawSearch = trim($rawSearch);
		$tokens    = self::tokenize($rawSearch);

		if ($tokens === []) {
			return [];
		}

		$qb = $this->createQueryBuilder('product')
			->select('product')
			->leftJoin('product.category', 'category')
			->where('product.store = :store')
			->andWhere('product.deletedAt IS NULL')
			->andWhere('product.status = :activeStatus')
			->setParameter('store', $store)
			->setParameter('activeStatus', ActiveStatusEnum::ACTIVE)
			->orderBy('product.name', 'ASC')
			->setMaxResults($limit);

		foreach ($tokens as $i => $token) {
			$likePattern = '%' . str_replace(['%', '_'], ['\%', '\_'], $token) . '%';
			$paramName   = 'tok_' . $i;
			$ppAlias     = 'pp_' . $i;

			$subQb = $this->getEntityManager()->createQueryBuilder()
				->select("$ppAlias.id")
				->from(ProductParameter::class, $ppAlias)
				->where("$ppAlias.product = product")
				->andWhere("LOWER($ppAlias.value) LIKE :$paramName");

			$qb->andWhere(
				$qb->expr()->orX(
					"LOWER(product.name) LIKE :$paramName",
					"LOWER(product.code) LIKE :$paramName",
					"LOWER(category.name) LIKE :$paramName",
					$qb->expr()->exists($subQb->getDQL()),
				)
			)->setParameter($paramName, $likePattern);
		}

		return $qb->getQuery()->getResult();
	}

	private static function tokenize(string $raw): array
	{
		$normalized = trim(mb_strtolower($raw));
		$normalized = (string) preg_replace('/[\-\/\.,\_]+/', ' ', $normalized);
		$normalized = (string) preg_replace('/\s+/', ' ', $normalized);

		return array_values(array_filter(explode(' ', $normalized)));
	}

	/**
	 * @return Product[]
	 */
	public function findChoicesByStoreAndSearch(Store $store, string $search, int $limit = 20, int $offset = 0): array
	{
		return $this->findAvailableByStoreQB($store)
			->andWhere('product.name like :search OR product.code like :search')
			->setParameter('search', '%' . $search . '%')
			->orderBy('product.name', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset)
			->getQuery()
			->getResult();
	}

	/**
	 * @return Product[]
	 */
	public function findPurchasableChoicesByStoreAndSearch(Store $store, string $search, int $limit = 20, int $offset = 0): array
	{
		return $this->findAvailableByStoreQB($store)
			->andWhere('product.canBePurchased = :canBePurchased')
			->andWhere('product.name like :search OR product.code like :search')
			->setParameter('canBePurchased', true)
			->setParameter('search', '%' . $search . '%')
			->orderBy('product.name', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset)
			->getQuery()
			->getResult();
	}

	public function findManufacturableByStoreQB(Store $store): QueryBuilder
	{
		return $this->findAvailableByStoreQB($store)
			->andWhere('product.canBeManufactured = :canBeManufactured')
			->setParameter('canBeManufactured', true);
	}

	public function findProductionMaterialsByStoreQB(Store $store): QueryBuilder
	{
		return $this->findAvailableByStoreQB($store)
			->andWhere('product.productKind = :materialKind')
			->setParameter('materialKind', ProductKindEnum::MATERIAL);
	}

	/**
	 * @return Product[]
	 */
	public function findMaterialChoicesByStoreAndSearch(Store $store, string $search, int $limit = 20, int $offset = 0): array
	{
		return $this->findProductionMaterialsByStoreQB($store)
			->andWhere('product.name like :search OR product.code like :search')
			->setParameter('search', '%' . $search . '%')
			->orderBy('product.name', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset)
			->getQuery()
			->getResult();
	}

}

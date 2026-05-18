<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Store;
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

}

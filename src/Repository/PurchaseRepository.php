<?php

namespace App\Repository;

use App\Entity\Purchase;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Purchase>
 *
 * @method Purchase|null find($id, $lockMode = null, $lockVersion = null)
 * @method Purchase|null findOneBy(array $criteria, array $orderBy = null)
 * @method Purchase[]    findAll()
 * @method Purchase[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purchase::class);
    }

    public function add(Purchase $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Purchase $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('purchase')
			->leftJoin('purchase.supplier', 'supplier')
			->addSelect('supplier')
			->innerJoin('purchase.currency', 'currency')
			->addSelect('currency')
			->leftJoin('purchase.purchaseEntries', 'purchaseEntry')
			->addSelect('purchaseEntry')
			->andWhere('purchase.store = :store')
			->setParameter('store', $store);
	}

	public function getNextNumber(Store $store): string
	{
		$count = (int) $this->createQueryBuilder('purchase')
			->select('COUNT(purchase.id)')
			->andWhere('purchase.store = :store')
			->setParameter('store', $store)
			->getQuery()
			->getSingleScalarResult();

		return sprintf('PO-%d-%06d', $store->getId(), $count + 1);
	}

	/**
	 * @return Purchase[]
	 */
	public function findChoicesByStoreAndSearch(Store $store, string $search, int $limit = 20): array
	{
		return $this->createQueryBuilder('purchase')
			->andWhere('purchase.store = :store')
			->andWhere('purchase.number LIKE :search OR purchase.supplierNameSnapshot LIKE :search')
			->setParameter('store', $store)
			->setParameter('search', '%' . $search . '%')
			->orderBy('purchase.id', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}

	public function getIndexPage(Purchase $purchase, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('purchase')
			->select('COUNT(purchase.id)')
			->andWhere('purchase.store = :store')
			->andWhere('purchase.id >= :id')
			->setParameter('store', $purchase->getStore())
			->setParameter('id', $purchase->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}

//    /**
//     * @return Purchase[] Returns an array of Purchase objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Purchase
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

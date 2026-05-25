<?php

namespace App\Repository;

use App\Entity\PurchaseEntry;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseEntry>
 *
 * @method PurchaseEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseEntry[]    findAll()
 * @method PurchaseEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseEntry::class);
    }

    public function add(PurchaseEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PurchaseEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findReturnableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('purchaseEntry')
			->innerJoin('purchaseEntry.purchase', 'purchase')
			->addSelect('purchase')
			->innerJoin('purchaseEntry.product', 'product')
			->addSelect('product')
			->innerJoin('purchaseEntry.warehouse', 'warehouse')
			->addSelect('warehouse')
			->andWhere('purchase.store = :store')
			->andWhere('COALESCE(purchaseEntry.receivedQuantity, 0) > COALESCE(purchaseEntry.returnedQuantity, 0)')
			->setParameter('store', $store)
			->orderBy('purchase.id', 'DESC')
			->addOrderBy('purchaseEntry.id', 'ASC');
	}

//    /**
//     * @return PurchaseEntry[] Returns an array of PurchaseEntry objects
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

//    public function findOneBySomeField($value): ?PurchaseEntry
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

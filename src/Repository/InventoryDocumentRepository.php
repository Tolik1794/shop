<?php

namespace App\Repository;

use App\Entity\InventoryDocument;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryDocument>
 *
 * @method InventoryDocument|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryDocument|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryDocument[]    findAll()
 * @method InventoryDocument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryDocumentRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, InventoryDocument::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('inventoryDocument')
			->leftJoin('inventoryDocument.reason', 'reason')
			->addSelect('reason')
			->leftJoin('inventoryDocument.order', 'orders')
			->addSelect('orders')
			->leftJoin('inventoryDocument.purchase', 'purchase')
			->addSelect('purchase')
			->andWhere('inventoryDocument.store = :store')
			->setParameter('store', $store);
	}
}

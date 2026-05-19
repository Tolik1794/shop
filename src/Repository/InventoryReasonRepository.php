<?php

namespace App\Repository;

use App\Entity\InventoryReason;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryReason>
 *
 * @method InventoryReason|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryReason|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryReason[]    findAll()
 * @method InventoryReason[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryReasonRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, InventoryReason::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('inventoryReason')
			->andWhere('inventoryReason.store = :store')
			->andWhere('inventoryReason.status != :deleted')
			->setParameter('store', $store)
			->setParameter('deleted', ActiveStatusEnum::DELETED);
	}
}

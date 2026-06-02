<?php

namespace App\Repository;

use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerLabel>
 *
 * @method CustomerLabel|null find($id, $lockMode = null, $lockVersion = null)
 * @method CustomerLabel|null findOneBy(array $criteria, array $orderBy = null)
 * @method CustomerLabel[]    findAll()
 * @method CustomerLabel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CustomerLabelRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, CustomerLabel::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('customerLabel')
			->innerJoin('customerLabel.store', 'store')
			->where('store = :store')
			->andWhere('customerLabel.deletedAt IS NULL')
			->setParameter('store', $store);
	}

	public function activeByStoreQB(Store $store): QueryBuilder
	{
		return $this->findAvailableByStoreQB($store)
			->andWhere('customerLabel.status = :activeStatus')
			->setParameter('activeStatus', ActiveStatusEnum::ACTIVE)
			->orderBy('customerLabel.sortOrder', 'ASC')
			->addOrderBy('customerLabel.name', 'ASC');
	}
}

<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 *
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Payment::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('payment')
			->innerJoin('payment.currency', 'currency')
			->addSelect('currency')
			->leftJoin('payment.order', 'orders')
			->addSelect('orders')
			->leftJoin('payment.purchase', 'purchase')
			->addSelect('purchase')
			->leftJoin('payment.reversedByPayment', 'reversedByPayment')
			->addSelect('reversedByPayment')
			->andWhere('payment.store = :store')
			->setParameter('store', $store);
	}
}

<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\PaymentHistory;
use App\Entity\Purchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentHistory>
 */
class PaymentHistoryRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, PaymentHistory::class);
	}

	/**
	 * @return PaymentHistory[]
	 */
	public function findTimelineByOrder(Order $order): array
	{
		return $this->createQueryBuilder('paymentHistory')
			->leftJoin('paymentHistory.actor', 'actor')
			->addSelect('actor')
			->leftJoin('paymentHistory.payment', 'payment')
			->addSelect('payment')
			->andWhere('paymentHistory.order = :order')
			->setParameter('order', $order)
			->orderBy('paymentHistory.occurredAt', 'DESC')
			->addOrderBy('paymentHistory.id', 'DESC')
			->getQuery()
			->getResult();
	}

	/**
	 * @return PaymentHistory[]
	 */
	public function findTimelineByPurchase(Purchase $purchase): array
	{
		return $this->createQueryBuilder('paymentHistory')
			->leftJoin('paymentHistory.actor', 'actor')
			->addSelect('actor')
			->leftJoin('paymentHistory.payment', 'payment')
			->addSelect('payment')
			->andWhere('paymentHistory.purchase = :purchase')
			->setParameter('purchase', $purchase)
			->orderBy('paymentHistory.occurredAt', 'DESC')
			->addOrderBy('paymentHistory.id', 'DESC')
			->getQuery()
			->getResult();
	}
}

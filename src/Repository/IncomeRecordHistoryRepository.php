<?php

namespace App\Repository;

use App\Entity\IncomeRecordHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IncomeRecordHistory>
 *
 * @method IncomeRecordHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method IncomeRecordHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method IncomeRecordHistory[]    findAll()
 * @method IncomeRecordHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncomeRecordHistoryRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, IncomeRecordHistory::class);
	}
}

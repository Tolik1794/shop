<?php

namespace App\Repository;

use App\Entity\IncomeRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IncomeRecord>
 *
 * @method IncomeRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method IncomeRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method IncomeRecord[]    findAll()
 * @method IncomeRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncomeRecordRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, IncomeRecord::class);
	}
}

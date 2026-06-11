<?php

namespace App\Repository;

use App\Entity\TaxReportingPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxReportingPeriod>
 *
 * @method TaxReportingPeriod|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxReportingPeriod|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaxReportingPeriod[]    findAll()
 * @method TaxReportingPeriod[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaxReportingPeriodRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, TaxReportingPeriod::class);
	}
}

<?php

namespace App\Repository;

use App\Entity\TaxReportDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxReportDraft>
 *
 * @method TaxReportDraft|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxReportDraft|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaxReportDraft[]    findAll()
 * @method TaxReportDraft[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaxReportDraftRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, TaxReportDraft::class);
	}
}

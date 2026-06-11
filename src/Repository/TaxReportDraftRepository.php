<?php

namespace App\Repository;

use App\Entity\LegalEntity;
use App\Entity\TaxReportDraft;
use DateTimeImmutable;
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

	/**
	 * @return TaxReportDraft[]
	 */
	public function findForEntityYear(LegalEntity $entity, int $year): array
	{
		return $this->createQueryBuilder('draft')
			->innerJoin('draft.period', 'period')
			->addSelect('period')
			->where('draft.legalEntity = :entity')
			->andWhere('period.dateFrom >= :from')
			->andWhere('period.dateTo <= :to')
			->setParameter('entity', $entity)
			->setParameter('from', new DateTimeImmutable("{$year}-01-01"))
			->setParameter('to', new DateTimeImmutable("{$year}-12-31"))
			->orderBy('period.dateFrom', 'ASC')
			->addOrderBy('draft.id', 'DESC')
			->getQuery()
			->getResult();
	}
}

<?php

namespace App\Repository;

use App\Entity\LegalEntity;
use App\Entity\TaxReportingPeriod;
use App\Enum\TaxPeriodStatusEnum;
use App\Enum\TaxPeriodTypeEnum;
use DateTimeImmutable;
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

	public function findOrCreate(
		LegalEntity $entity,
		TaxPeriodTypeEnum $type,
		DateTimeImmutable $dateFrom,
		DateTimeImmutable $dateTo,
	): TaxReportingPeriod {
		$existing = $this->findOneBy([
			'legalEntity' => $entity,
			'type'        => $type,
			'dateFrom'    => $dateFrom,
		]);

		if ($existing instanceof TaxReportingPeriod) {
			return $existing;
		}

		$period = (new TaxReportingPeriod())
			->setLegalEntity($entity)
			->setType($type)
			->setDateFrom($dateFrom)
			->setDateTo($dateTo);

		$this->getEntityManager()->persist($period);

		return $period;
	}

	/**
	 * First closed or declared period of the legal entity covering the given date.
	 */
	public function findBlockingPeriod(LegalEntity $entity, DateTimeImmutable $date): ?TaxReportingPeriod
	{
		return $this->createQueryBuilder('period')
			->where('period.legalEntity = :entity')
			->andWhere('period.dateFrom <= :date')
			->andWhere('period.dateTo >= :date')
			->andWhere('period.status IN (:statuses)')
			->setParameter('entity', $entity)
			->setParameter('date', $date->format('Y-m-d'))
			->setParameter('statuses', [TaxPeriodStatusEnum::CLOSED, TaxPeriodStatusEnum::DECLARED])
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}

	/**
	 * @return TaxReportingPeriod[]
	 */
	public function findForEntityYear(LegalEntity $entity, int $year): array
	{
		return $this->createQueryBuilder('period')
			->where('period.legalEntity = :entity')
			->andWhere('period.dateFrom >= :from')
			->andWhere('period.dateTo <= :to')
			->setParameter('entity', $entity)
			->setParameter('from', "{$year}-01-01")
			->setParameter('to', "{$year}-12-31")
			->orderBy('period.dateFrom', 'ASC')
			->addOrderBy('period.type', 'ASC')
			->getQuery()
			->getResult();
	}
}

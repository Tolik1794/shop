<?php

namespace App\Repository;

use App\Entity\LegalEntity;
use App\Entity\TaxReportingPeriod;
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
}

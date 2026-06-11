<?php

namespace App\Repository;

use App\Entity\LegalEntity;
use App\Entity\TaxAccrual;
use App\Entity\TaxReportingPeriod;
use App\Enum\TaxTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxAccrual>
 *
 * @method TaxAccrual|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxAccrual|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaxAccrual[]    findAll()
 * @method TaxAccrual[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaxAccrualRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, TaxAccrual::class);
	}

	public function findOrCreate(
		LegalEntity $entity,
		TaxReportingPeriod $period,
		TaxTypeEnum $taxType,
		string $accruedAmount,
		DateTimeImmutable $dueDate,
	): TaxAccrual {
		$existing = $this->findOneBy([
			'legalEntity' => $entity,
			'period'      => $period,
			'taxType'     => $taxType,
		]);

		if ($existing instanceof TaxAccrual) {
			return $existing;
		}

		$accrual = (new TaxAccrual())
			->setLegalEntity($entity)
			->setPeriod($period)
			->setTaxType($taxType)
			->setAccruedAmount($accruedAmount)
			->setDueDate($dueDate);

		$this->getEntityManager()->persist($accrual);

		return $accrual;
	}

	/**
	 * @return TaxAccrual[]
	 */
	public function findForEntityYear(LegalEntity $entity, int $year): array
	{
		return $this->createQueryBuilder('a')
			->innerJoin('a.period', 'p')
			->addSelect('p')
			->where('a.legalEntity = :entity')
			->andWhere('p.dateFrom >= :from')
			->andWhere('p.dateTo <= :to')
			->setParameter('entity', $entity)
			->setParameter('from', new DateTimeImmutable("{$year}-01-01"))
			->setParameter('to', new DateTimeImmutable("{$year}-12-31"))
			->orderBy('a.dueDate', 'ASC')
			->addOrderBy('a.taxType', 'ASC')
			->getQuery()
			->getResult();
	}

	/**
	 * @return TaxAccrual[]
	 */
	public function findFilteredQB(\Doctrine\ORM\QueryBuilder $qb = null): \Doctrine\ORM\QueryBuilder
	{
		return $this->createQueryBuilder('accrual')
			->innerJoin('accrual.period', 'period')
			->addSelect('period')
			->innerJoin('accrual.legalEntity', 'legal_entity')
			->addSelect('legal_entity');
	}
}

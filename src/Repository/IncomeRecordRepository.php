<?php

namespace App\Repository;

use App\Entity\IncomeRecord;
use App\Enum\IncomeClassificationEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

	public function findFilteredQB(): QueryBuilder
	{
		return $this->createQueryBuilder('income_record')
			->innerJoin('income_record.legalEntity', 'legal_entity')
			->addSelect('legal_entity')
			->innerJoin('income_record.currency', 'currency')
			->addSelect('currency')
			->leftJoin('income_record.store', 'store')
			->addSelect('store');
	}

	/**
	 * Sums amountUah per classification for the filtered query.
	 *
	 * @return array<string, string> classification value => sum (decimal string)
	 */
	public function sumByClassification(QueryBuilder $filteredQueryBuilder): array
	{
		$sumQueryBuilder = (clone $filteredQueryBuilder)
			->select('income_record.classification AS classification', 'SUM(income_record.amountUah) AS total')
			->groupBy('income_record.classification')
			->resetDQLPart('orderBy');

		$sums = [];
		foreach ($sumQueryBuilder->getQuery()->getArrayResult() as $row) {
			$classification = $row['classification'] instanceof IncomeClassificationEnum
				? $row['classification']->value
				: (string) $row['classification'];
			$sums[$classification] = (string) $row['total'];
		}

		return $sums;
	}
}

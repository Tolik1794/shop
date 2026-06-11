<?php

namespace App\Repository;

use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Enum\IncomeClassificationEnum;
use DateTimeImmutable;
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

	/**
	 * Net income (income - refund) in UAH for a calendar year.
	 */
	public function getNetIncomeForYear(LegalEntity $entity, int $year): string
	{
		$income = IncomeClassificationEnum::INCOME->value;
		$refund = IncomeClassificationEnum::REFUND->value;
		$from = "{$year}-01-01";
		$to = "{$year}-12-31";

		$result = $this->getEntityManager()->getConnection()->fetchOne(
			"SELECT COALESCE(
                SUM(CASE WHEN classification = ? THEN amount_uah ELSE 0 END)
                - SUM(CASE WHEN classification = ? THEN amount_uah ELSE 0 END),
                0
            )
            FROM income_record
            WHERE legal_entity_id = ?
              AND recognized_at BETWEEN ? AND ?
              AND classification IN (?, ?)",
			[$income, $refund, $entity->getId(), $from, $to, $income, $refund],
		);

		return number_format((float) ($result ?? 0), 4, '.', '');
	}

	/**
	 * Average daily income (UAH) over last N days (divides total by N, not by days-with-data).
	 */
	public function getAvgDailyIncome(LegalEntity $entity, int $days): string
	{
		$from = (new DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d');

		$result = $this->getEntityManager()->getConnection()->fetchOne(
			'SELECT COALESCE(SUM(amount_uah), 0) / ?
             FROM income_record
             WHERE legal_entity_id = ?
               AND recognized_at >= ?
               AND classification = ?',
			[$days, $entity->getId(), $from, IncomeClassificationEnum::INCOME->value],
		);

		return number_format((float) ($result ?? 0), 4, '.', '');
	}

	/**
	 * Monthly breakdown for a year.
	 *
	 * @return array<int, array{income: string, refund: string, net: string}> keyed by month 1-12
	 */
	public function getMonthlyBreakdown(LegalEntity $entity, int $year): array
	{
		$income = IncomeClassificationEnum::INCOME->value;
		$refund = IncomeClassificationEnum::REFUND->value;
		$from = "{$year}-01-01";
		$to = "{$year}-12-31";

		$rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
			"SELECT EXTRACT(MONTH FROM recognized_at)::int AS month,
                    COALESCE(SUM(CASE WHEN classification = ? THEN amount_uah ELSE 0 END), 0) AS income,
                    COALESCE(SUM(CASE WHEN classification = ? THEN amount_uah ELSE 0 END), 0) AS refund
             FROM income_record
             WHERE legal_entity_id = ?
               AND recognized_at BETWEEN ? AND ?
               AND classification IN (?, ?)
             GROUP BY month
             ORDER BY month",
			[$income, $refund, $entity->getId(), $from, $to, $income, $refund],
		);

		$byMonth = [];
		foreach ($rows as $row) {
			$m = (int) $row['month'];
			$inc = (float) $row['income'];
			$ref = (float) $row['refund'];
			$byMonth[$m] = [
				'income' => number_format($inc, 4, '.', ''),
				'refund' => number_format($ref, 4, '.', ''),
				'net'    => number_format($inc - $ref, 4, '.', ''),
			];
		}

		return $byMonth;
	}
}

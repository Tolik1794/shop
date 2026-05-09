<?php

namespace App\Repository;

use App\Entity\Currency;
use App\Entity\ExchangeRate;
use App\Entity\Store;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 *
 * @method ExchangeRate|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExchangeRate|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExchangeRate[]    findAll()
 * @method ExchangeRate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExchangeRateRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ExchangeRate::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('exchangeRate')
			->leftJoin('exchangeRate.store', 'store')
			->addSelect('store')
			->innerJoin('exchangeRate.fromCurrency', 'fromCurrency')
			->addSelect('fromCurrency')
			->innerJoin('exchangeRate.toCurrency', 'toCurrency')
			->addSelect('toCurrency')
			->where('exchangeRate.store = :store OR exchangeRate.store IS NULL')
			->setParameter('store', $store);
	}

	public function findRateForDate(
		Currency $fromCurrency,
		Currency $toCurrency,
		?Store $store,
		DateTimeImmutable $date,
	): ?ExchangeRate {
		if ($store instanceof Store) {
			$storeRate = $this->findRateForDateByStore($fromCurrency, $toCurrency, $store, $date);
			if ($storeRate instanceof ExchangeRate) {
				return $storeRate;
			}
		}

		return $this->findRateForDateByStore($fromCurrency, $toCurrency, null, $date);
	}

	public function hasOverlappingRate(ExchangeRate $exchangeRate): bool
	{
		if (!$exchangeRate->getFromCurrency() || !$exchangeRate->getToCurrency()) {
			return false;
		}

		$qb = $this->createQueryBuilder('exchangeRate')
			->select('COUNT(exchangeRate.id)')
			->andWhere('exchangeRate.fromCurrency = :fromCurrency')
			->andWhere('exchangeRate.toCurrency = :toCurrency')
			->andWhere('exchangeRate.validFrom <= COALESCE(:validTo, exchangeRate.validFrom)')
			->andWhere('COALESCE(exchangeRate.validTo, :validFrom) >= :validFrom')
			->setParameter('fromCurrency', $exchangeRate->getFromCurrency())
			->setParameter('toCurrency', $exchangeRate->getToCurrency())
			->setParameter('validFrom', $exchangeRate->getValidFrom())
			->setParameter('validTo', $exchangeRate->getValidTo());

		if ($exchangeRate->getStore() instanceof Store) {
			$qb->andWhere('exchangeRate.store = :store')
				->setParameter('store', $exchangeRate->getStore());
		} else {
			$qb->andWhere('exchangeRate.store IS NULL');
		}

		if ($exchangeRate->getId()) {
			$qb->andWhere('exchangeRate.id != :id')
				->setParameter('id', $exchangeRate->getId());
		}

		return (int) $qb->getQuery()->getSingleScalarResult() > 0;
	}

	public function hasBlockingRateForNewStart(ExchangeRate $exchangeRate): bool
	{
		if (!$exchangeRate->getFromCurrency() || !$exchangeRate->getToCurrency()) {
			return false;
		}

		$qb = $this->createQueryBuilder('exchangeRate')
			->select('COUNT(exchangeRate.id)')
			->andWhere('exchangeRate.fromCurrency = :fromCurrency')
			->andWhere('exchangeRate.toCurrency = :toCurrency')
			->andWhere(
				'(exchangeRate.validFrom = :validFrom'
				. ' OR (exchangeRate.validFrom < :validFrom AND exchangeRate.validTo IS NOT NULL AND exchangeRate.validTo > :validFrom)'
				. ' OR exchangeRate.validFrom > :validFrom)'
			)
			->setParameter('fromCurrency', $exchangeRate->getFromCurrency())
			->setParameter('toCurrency', $exchangeRate->getToCurrency())
			->setParameter('validFrom', $exchangeRate->getValidFrom());

		if ($exchangeRate->getStore() instanceof Store) {
			$qb->andWhere('exchangeRate.store = :store')
				->setParameter('store', $exchangeRate->getStore());
		} else {
			$qb->andWhere('exchangeRate.store IS NULL');
		}

		return (int) $qb->getQuery()->getSingleScalarResult() > 0;
	}

	public function findOpenRateBefore(ExchangeRate $exchangeRate): ?ExchangeRate
	{
		if (!$exchangeRate->getFromCurrency() || !$exchangeRate->getToCurrency()) {
			return null;
		}

		$qb = $this->createQueryBuilder('exchangeRate')
			->andWhere('exchangeRate.fromCurrency = :fromCurrency')
			->andWhere('exchangeRate.toCurrency = :toCurrency')
			->andWhere('exchangeRate.validTo IS NULL')
			->andWhere('exchangeRate.validFrom < :validFrom')
			->setParameter('fromCurrency', $exchangeRate->getFromCurrency())
			->setParameter('toCurrency', $exchangeRate->getToCurrency())
			->setParameter('validFrom', $exchangeRate->getValidFrom())
			->orderBy('exchangeRate.validFrom', 'DESC')
			->setMaxResults(1);

		if ($exchangeRate->getStore() instanceof Store) {
			$qb->andWhere('exchangeRate.store = :store')
				->setParameter('store', $exchangeRate->getStore());
		} else {
			$qb->andWhere('exchangeRate.store IS NULL');
		}

		return $qb->getQuery()->getOneOrNullResult();
	}

	private function findRateForDateByStore(
		Currency $fromCurrency,
		Currency $toCurrency,
		?Store $store,
		DateTimeImmutable $date,
	): ?ExchangeRate {
		$qb = $this->createQueryBuilder('exchangeRate')
			->andWhere('exchangeRate.fromCurrency = :fromCurrency')
			->andWhere('exchangeRate.toCurrency = :toCurrency')
			->andWhere('exchangeRate.validFrom <= :date')
			->andWhere('exchangeRate.validTo IS NULL OR exchangeRate.validTo > :date')
			->setParameter('fromCurrency', $fromCurrency)
			->setParameter('toCurrency', $toCurrency)
			->setParameter('date', $date)
			->orderBy('exchangeRate.validFrom', 'DESC')
			->setMaxResults(1);

		if ($store instanceof Store) {
			$qb->andWhere('exchangeRate.store = :store')
				->setParameter('store', $store);
		} else {
			$qb->andWhere('exchangeRate.store IS NULL');
		}

		return $qb->getQuery()->getOneOrNullResult();
	}
}

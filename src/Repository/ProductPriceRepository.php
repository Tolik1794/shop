<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Entity\Currency;
use App\Enum\ProductPriceTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductPrice>
 *
 * @method ProductPrice|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductPrice|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductPrice[]    findAll()
 * @method ProductPrice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductPriceRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, ProductPrice::class);
	}

	public function findAvailableByProductQB(Product $product): QueryBuilder
	{
		return $this->createQueryBuilder('productPrice')
			->innerJoin('productPrice.product', 'product')
			->addSelect('product')
			->innerJoin('productPrice.currency', 'currency')
			->addSelect('currency')
			->andWhere('productPrice.product = :product')
			->setParameter('product', $product);
	}

	public function getIndexPage(ProductPrice $productPrice, int $limit = 20): int
	{
		$count = (int) $this->createQueryBuilder('productPrice')
			->select('COUNT(productPrice.id)')
			->andWhere('productPrice.product = :product')
			->andWhere('productPrice.validFrom > :validFrom OR (productPrice.validFrom = :validFrom AND productPrice.id >= :id)')
			->setParameter('product', $productPrice->getProduct())
			->setParameter('validFrom', $productPrice->getValidFrom())
			->setParameter('id', $productPrice->getId())
			->getQuery()
			->getSingleScalarResult();

		return max(1, (int) ceil($count / $limit));
	}

	public function findCurrentRegularPrice(Product $product, ?Currency $currency = null, ?DateTimeImmutable $date = null): ?ProductPrice
	{
		$date ??= new DateTimeImmutable();

		$qb = $this->createQueryBuilder('productPrice')
			->andWhere('productPrice.product = :product')
			->andWhere('productPrice.type = :type')
			->andWhere('productPrice.validFrom <= :date')
			->andWhere('productPrice.validTo IS NULL OR productPrice.validTo > :date')
			->setParameter('product', $product)
			->setParameter('type', ProductPriceTypeEnum::REGULAR)
			->setParameter('date', $date)
			->orderBy('productPrice.validFrom', 'DESC')
			->setMaxResults(1);

		if ($currency instanceof Currency) {
			$qb->andWhere('productPrice.currency = :currency')
				->setParameter('currency', $currency);
		}

		return $qb->getQuery()->getOneOrNullResult();
	}

	public function hasBlockingPriceForNewStart(ProductPrice $productPrice): bool
	{
		if (!$this->hasRequiredRelations($productPrice)) {
			return false;
		}

		$qb = $this->createSameTimelineQueryBuilder($productPrice)
			->select('COUNT(productPrice.id)')
			->andWhere(
				'(productPrice.validFrom = :validFrom'
				. ' OR (productPrice.validFrom < :validFrom AND productPrice.validTo IS NOT NULL AND productPrice.validTo > :validFrom)'
				. ' OR productPrice.validFrom > :validFrom)'
			)
			->setParameter('validFrom', $productPrice->getValidFrom());

		return (int) $qb->getQuery()->getSingleScalarResult() > 0;
	}

	public function hasOverlappingPrice(ProductPrice $productPrice): bool
	{
		if (!$this->hasRequiredRelations($productPrice)) {
			return false;
		}

		$qb = $this->createSameTimelineQueryBuilder($productPrice)
			->select('COUNT(productPrice.id)')
			->andWhere('productPrice.validFrom <= COALESCE(:validTo, productPrice.validFrom)')
			->andWhere('COALESCE(productPrice.validTo, :validFrom) > :validFrom')
			->setParameter('validFrom', $productPrice->getValidFrom())
			->setParameter('validTo', $productPrice->getValidTo());

		if ($productPrice->getId()) {
			$qb->andWhere('productPrice.id != :id')
				->setParameter('id', $productPrice->getId());
		}

		return (int) $qb->getQuery()->getSingleScalarResult() > 0;
	}

	public function findOpenPriceBefore(ProductPrice $productPrice): ?ProductPrice
	{
		if (!$this->hasRequiredRelations($productPrice)) {
			return null;
		}

		return $this->createSameTimelineQueryBuilder($productPrice)
			->andWhere('productPrice.validTo IS NULL')
			->andWhere('productPrice.validFrom < :validFrom')
			->setParameter('validFrom', $productPrice->getValidFrom())
			->orderBy('productPrice.validFrom', 'DESC')
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}

	private function createSameTimelineQueryBuilder(ProductPrice $productPrice): QueryBuilder
	{
		return $this->createQueryBuilder('productPrice')
			->andWhere('productPrice.store = :store')
			->andWhere('productPrice.product = :product')
			->andWhere('productPrice.type = :type')
			->andWhere('productPrice.currency = :currency')
			->setParameter('store', $productPrice->getStore())
			->setParameter('product', $productPrice->getProduct())
			->setParameter('type', $productPrice->getType())
			->setParameter('currency', $productPrice->getCurrency());
	}

	private function hasRequiredRelations(ProductPrice $productPrice): bool
	{
		return $productPrice->getStore() instanceof Store
			&& $productPrice->getProduct() instanceof Product
			&& $productPrice->getCurrency() !== null;
	}
}

<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 *
 * @method Customer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Customer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Customer[]    findAll()
 * @method Customer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CustomerRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Customer::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('customer')
			->andWhere('customer.store = :store')
			->andWhere('customer.deletedAt IS NULL')
			->setParameter('store', $store);
	}

	public function findOneAvailableByStoreAndPhone(Store $store, string $phone): ?Customer
	{
		$phone = Customer::normalizePhone($phone) ?? $phone;

		return $this->findAvailableByStoreQB($store)
			->andWhere('customer.phone = :phone')
			->setParameter('phone', $phone)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}

	/**
	 * @return Customer[]
	 */
	public function findAvailableByStoreAndPhoneSearch(Store $store, string $phone, int $limit = 10): array
	{
		$terms = $this->phoneSearchTerms($phone);
		$queryBuilder = $this->findAvailableByStoreQB($store);
		$orWhere = $queryBuilder->expr()->orX();

		foreach ($terms as $index => $term) {
			$parameter = 'phone' . $index;
			$orWhere->add('customer.phone LIKE :' . $parameter);
			$queryBuilder->setParameter($parameter, '%' . $term . '%');
		}

		return $queryBuilder
			->andWhere($orWhere)
			->orderBy('customer.phone', 'ASC')
			->addOrderBy('customer.name', 'ASC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}

	/**
	 * @return string[]
	 */
	private function phoneSearchTerms(string $phone): array
	{
		$phone = trim($phone);
		$digits = preg_replace('/\D+/', '', $phone) ?? '';
		$terms = array_filter([$phone, $digits]);

		if (str_starts_with($digits, '0')) {
			$terms[] = '+38' . $digits;
			$terms[] = '38' . $digits;
		}

		if (str_starts_with($digits, '380')) {
			$terms[] = '+' . $digits;
		}

		return array_values(array_unique($terms));
	}
}

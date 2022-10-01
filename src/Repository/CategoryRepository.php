<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Store;
use App\Tools\SqlHelperTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 *
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends ServiceEntityRepository
{
	use SqlHelperTrait;

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Category::class);
	}

	public function add(Category $entity, bool $flush = false): void
	{
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function remove(Category $entity, bool $flush = false): void
	{
		$this->getEntityManager()->remove($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function findAvailableCategories(Store $store, int $countOfChildren = 2): ?iterable
	{
		$categoryColumns = ['id', 'name', 'description'];
		$categoryRelationColumns = ['store_id', 'parent_id'];
		$categoryAllColumns = array_merge($categoryColumns, $categoryRelationColumns);

		$rsm = (new ResultSetMapping)
			->addEntityResult(Category::class, 'cte')
			->addJoinedEntityResult(Category::class, 'p', 'cte', 'parent');

		foreach ($categoryColumns as $column) {
			$rsm->addFieldResult('cte', $column, $column)
				->addFieldResult('p', sprintf('%s_%s', 'par', $column), $column);
		}
		unset($column);

		$columnsStr = $this->columnsToStr($categoryAllColumns);

		$query = $this->getEntityManager()->createNativeQuery(
			'WITH RECURSIVE cte (' . $columnsStr . ', first, count) AS (
					SELECT ' . $columnsStr . ', id as first, 0
					FROM category
					WHERE parent_id IS null AND store_id = :store
					UNION ALL
					SELECT ' . $this->columnsToStr($categoryAllColumns, 'c') . ', cte.first as first, cte.count + 1
					FROM category c
					INNER JOIN cte ON c.parent_id = cte.id AND c.store_id = cte.store_id
				)
				SELECT 
					' . $this->columnsToStr($categoryColumns, 'cte') . ',
					' . $this->columnsToStr($categoryColumns, 'p', 'par') . '
				FROM cte
				LEFT JOIN category p ON p.id = cte.parent_id
				WHERE cte.count <= :count
				AND cte.store_id = :store
				GROUP BY cte.id
				ORDER BY first
				',
			$rsm
		)->setParameters([
			'store' => $store->getId(),
			'count' => $countOfChildren,
		]);

		return $query->getResult();
	}

	public function findAvailableCategoriesQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('category')
			->where('category.store = :store')
			->setParameter('store', $store);
	}
}

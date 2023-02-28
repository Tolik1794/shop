<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Store;
use App\Tools\RepositoryHelperTrait;
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
	use RepositoryHelperTrait;

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

	/**
	 * @tag recursive_query
	 */
	public function findAvailableCategories(Store $store, int $levelOfChildren = 2, iterable $categories = null): ?iterable
	{
		$categoryColumns = ['id', 'name', 'description'];
		$categoryRelationColumns = ['store_id', 'parent_id'];
		$categoryAllColumns = array_merge($categoryColumns, $categoryRelationColumns);

		$rsm = (new ResultSetMapping)
			->addEntityResult(Category::class, 'cte')
			->addJoinedEntityResult(Category::class, 'p', 'cte', 'parent')
			->addFieldResult('p', 'first', 'firstId')
			->addFieldResult('p', 'level', 'level')
		;

		foreach ($categoryColumns as $column) {
			$rsm->addFieldResult('cte', $column, $column)
				->addFieldResult('p', sprintf('%s_%s', 'par', $column), $column);
		}
		unset($column);

		$columnsStr = $this->columnsToStr($categoryAllColumns);
		$parents = $categories ? "id in ({$this->implodeToSql($categories)})" : 'parent_id IS null';

		$sql = "WITH RECURSIVE cte ($columnsStr, first, level) AS (
					SELECT $columnsStr, id as first, 0
					FROM category
					WHERE store_id = :store 
						AND $parents
					UNION ALL
					SELECT {$this->columnsToStr($categoryAllColumns, 'c')}, cte.first as first, cte.level + 1
					FROM category c
					INNER JOIN cte ON c.parent_id = cte.id AND c.store_id = cte.store_id
				)
				SELECT 
					{$this->columnsToStr($categoryColumns, 'cte')},
					{$this->columnsToStr($categoryColumns, 'p', 'par')}
				FROM cte
				LEFT JOIN category p ON p.id = cte.parent_id
				WHERE cte.level <= :level
				AND cte.store_id = :store
				GROUP BY cte.id
				ORDER BY first, id
				";

		$query = $this->getEntityManager()->createNativeQuery(
			$sql,
			$rsm
		)->setParameters([
			'store' => $store->getId(),
			'level' => $levelOfChildren,
		]);

		return $query->getResult();
	}

	public function findAvailableCategoriesQB(Store $store, int $maxLevel = 2): QueryBuilder
	{
		return $this->createQueryBuilder('category')
			->leftJoin('category.parent', 'parent')
			->leftJoin('category.firstParent', 'first_parent')
			->where('category.store = :store')
			->andWhere('category.level <= :max_level')
			->orderBy('
				CASE 
					WHEN category.parent IS null 
					THEN category.id 
					ELSE first_parent.id 
				END, 
				category.id', 'ASC')
			->setParameter('store', $store)
			->setParameter('max_level', $maxLevel)
			;
	}
}

<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryProductParameterName>
 *
 * @method CategoryProductParameterName|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryProductParameterName|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryProductParameterName[]    findAll()
 * @method CategoryProductParameterName[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryProductParameterNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryProductParameterName::class);
    }

    public function add(CategoryProductParameterName $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CategoryProductParameterName $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAllByCategory(Category $category)
	{
		return $this->createQueryBuilder('category_product_parameter_name')
			->innerJoin('category_product_parameter_name.category', 'category')
			->where('category.id in (:categories)')
			->setParameter(
				'categories',
				$this->getEntityManager()->getRepository(Category::class)->findAllParentIdRecursive($category),
				ArrayParameterType::INTEGER
			)->getQuery()->execute();
	}

	/**
	 * @return CategoryProductParameterName[]
	 */
	public function findInheritedByParent(?Category $parent): array
	{
		if (!$parent) {
			return [];
		}

		return $this->createQueryBuilder('category_product_parameter_name')
			->addSelect('category', 'product_parameter_name')
			->innerJoin('category_product_parameter_name.category', 'category')
			->innerJoin('category_product_parameter_name.productParameterName', 'product_parameter_name')
			->where('category.id in (:categories)')
			->setParameter(
				'categories',
				$this->getEntityManager()->getRepository(Category::class)->findAllParentIdRecursive($parent),
				ArrayParameterType::INTEGER
			)
			->orderBy('category.level', 'DESC')
			->addOrderBy('product_parameter_name.name', 'ASC')
			->getQuery()
			->getResult();
	}
}

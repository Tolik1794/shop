<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\ProductParameterName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductParameterName>
 *
 * @method ProductParameterName|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductParameterName|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductParameterName[]    findAll()
 * @method ProductParameterName[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductParameterNameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductParameterName::class);
    }

    public function add(ProductParameterName $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductParameterName $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findUsedByCategory(Category $category): array|null
	{
		return $this->createQueryBuilder('product_parameter_name')
			->innerJoin('product_parameter_name.categoryProductParameterNames', 'category_product_parameter_name')
			->where('category_product_parameter_name.category in (:categories)')
			->setParameter(
				'categories',
				$this->getEntityManager()->getRepository(Category::class)
					->findAllParentIdRecursive($category, withSelf: false)
			)
			->getQuery()->execute();
	}
}

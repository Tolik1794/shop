<?php

namespace App\Repository;

use App\Entity\LegalEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalEntity>
 *
 * @method LegalEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method LegalEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method LegalEntity[]    findAll()
 * @method LegalEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LegalEntityRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, LegalEntity::class);
	}

	/**
	 * @return LegalEntity[]
	 */
	public function findActive(): array
	{
		return $this->createQueryBuilder('legal_entity')
			->andWhere('legal_entity.deletedAt IS NULL')
			->orderBy('legal_entity.name', 'ASC')
			->getQuery()
			->getResult();
	}
}

<?php

namespace App\Repository;

use App\Entity\TaxRateSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxRateSet>
 *
 * @method TaxRateSet|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxRateSet|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaxRateSet[]    findAll()
 * @method TaxRateSet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaxRateSetRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, TaxRateSet::class);
	}

	public function findByYear(int $year): ?TaxRateSet
	{
		return $this->findOneBy(['year' => $year]);
	}
}

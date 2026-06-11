<?php

namespace App\Repository;

use App\Entity\TaxAccrual;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxAccrual>
 *
 * @method TaxAccrual|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxAccrual|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaxAccrual[]    findAll()
 * @method TaxAccrual[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaxAccrualRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, TaxAccrual::class);
	}
}

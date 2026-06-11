<?php

namespace App\Repository;

use App\Entity\NbuExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NbuExchangeRate>
 *
 * @method NbuExchangeRate|null find($id, $lockMode = null, $lockVersion = null)
 * @method NbuExchangeRate|null findOneBy(array $criteria, array $orderBy = null)
 * @method NbuExchangeRate[]    findAll()
 * @method NbuExchangeRate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NbuExchangeRateRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, NbuExchangeRate::class);
	}
}

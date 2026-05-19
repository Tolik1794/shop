<?php

namespace App\Repository;

use App\Entity\InventoryDocumentLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryDocumentLine>
 *
 * @method InventoryDocumentLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryDocumentLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryDocumentLine[]    findAll()
 * @method InventoryDocumentLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryDocumentLineRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, InventoryDocumentLine::class);
	}
}

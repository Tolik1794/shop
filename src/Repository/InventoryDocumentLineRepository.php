<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\InventoryDocumentLine;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
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

	/**
	 * @return list<int>
	 */
	public function findDraftCustomerReturnOrderEntryIds(Order $order): array
	{
		$rows = $this->createQueryBuilder('inventoryDocumentLine')
			->select('DISTINCT IDENTITY(inventoryDocumentLine.orderEntry) AS orderEntryId')
			->innerJoin('inventoryDocumentLine.inventoryDocument', 'inventoryDocument')
			->andWhere('inventoryDocument.order = :order')
			->andWhere('inventoryDocument.type = :type')
			->andWhere('inventoryDocument.status = :status')
			->andWhere('inventoryDocumentLine.orderEntry IS NOT NULL')
			->setParameter('order', $order)
			->setParameter('type', InventoryDocumentType::CUSTOMER_RETURN)
			->setParameter('status', InventoryDocumentStatus::DRAFT)
			->getQuery()
			->getArrayResult();

		return array_values(array_map(
			static fn (array $row): int => (int) $row['orderEntryId'],
			$rows,
		));
	}
}

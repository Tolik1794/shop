<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\InventoryDocument;
use App\Entity\InventoryReason;
use App\Entity\Order;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryDocument>
 *
 * @method InventoryDocument|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryDocument|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryDocument[]    findAll()
 * @method InventoryDocument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryDocumentRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, InventoryDocument::class);
	}

	public function findAvailableByStoreQB(Store $store): QueryBuilder
	{
		return $this->createQueryBuilder('inventoryDocument')
			->leftJoin('inventoryDocument.reason', 'reason')
			->addSelect('reason')
			->leftJoin('inventoryDocument.order', 'orders')
			->addSelect('orders')
			->leftJoin('inventoryDocument.purchase', 'purchase')
			->addSelect('purchase')
			->andWhere('inventoryDocument.store = :store')
			->setParameter('store', $store);
	}

	/**
	 * Customer return documents across all of a customer's orders, newest first.
	 * Used by the quick customer history panel.
	 *
	 * @return InventoryDocument[]
	 */
	public function findCustomerReturnsByCustomer(Customer $customer, int $limit = 10): array
	{
		return $this->createQueryBuilder('inventoryDocument')
			->innerJoin('inventoryDocument.order', 'orders')
			->addSelect('orders')
			->andWhere('orders.customer = :customer')
			->andWhere('inventoryDocument.type = :customerReturnType')
			->setParameter('customer', $customer)
			->setParameter('customerReturnType', InventoryDocumentType::CUSTOMER_RETURN)
			->orderBy('inventoryDocument.documentDate', 'DESC')
			->addOrderBy('inventoryDocument.id', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}

	public function findOneForStoreWithDetails(Store $store, int $id): ?InventoryDocument
	{
		return $this->createQueryBuilder('inventoryDocument')
			->leftJoin('inventoryDocument.reason', 'reason')
			->addSelect('reason')
			->leftJoin('inventoryDocument.currency', 'currency')
			->addSelect('currency')
			->leftJoin('inventoryDocument.order', 'orders')
			->addSelect('orders')
			->leftJoin('inventoryDocument.purchase', 'purchase')
			->addSelect('purchase')
			->leftJoin('inventoryDocument.productionOrder', 'productionOrder')
			->addSelect('productionOrder')
			->leftJoin('inventoryDocument.reversedDocument', 'reversedDocument')
			->addSelect('reversedDocument')
			->leftJoin('inventoryDocument.lines', 'line')
			->addSelect('line')
			->leftJoin('line.product', 'product')
			->addSelect('product')
			->leftJoin('line.warehouse', 'warehouse')
			->addSelect('warehouse')
			->leftJoin('line.warehouseStock', 'warehouseStock')
			->addSelect('warehouseStock')
			->leftJoin('line.stockMovements', 'stockMovement')
			->addSelect('stockMovement')
			->andWhere('inventoryDocument.store = :store')
			->andWhere('inventoryDocument.id = :id')
			->setParameter('store', $store)
			->setParameter('id', $id)
			->orderBy('line.id', 'ASC')
			->addOrderBy('stockMovement.id', 'ASC')
			->getQuery()
			->getOneOrNullResult();
	}

	/**
	 * @return list<InventoryDocument>
	 */
	public function findRecentByReason(InventoryReason $reason, int $limit = 10): array
	{
		return $this->createQueryBuilder('inventoryDocument')
			->leftJoin('inventoryDocument.order', 'orders')
			->addSelect('orders')
			->leftJoin('inventoryDocument.purchase', 'purchase')
			->addSelect('purchase')
			->leftJoin('inventoryDocument.productionOrder', 'productionOrder')
			->addSelect('productionOrder')
			->andWhere('inventoryDocument.reason = :reason')
			->setParameter('reason', $reason)
			->orderBy('inventoryDocument.documentDate', 'DESC')
			->addOrderBy('inventoryDocument.id', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}

	public function hasDraftForOrder(Order $order, InventoryDocumentType $type): bool
	{
		return (bool) $this->createQueryBuilder('inventoryDocument')
			->select('1')
			->andWhere('inventoryDocument.order = :order')
			->andWhere('inventoryDocument.type = :type')
			->andWhere('inventoryDocument.status = :status')
			->setParameter('order', $order)
			->setParameter('type', $type)
			->setParameter('status', InventoryDocumentStatus::DRAFT)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}

	public function hasDraftForPurchase(Purchase $purchase, InventoryDocumentType $type): bool
	{
		return (bool) $this->createQueryBuilder('inventoryDocument')
			->select('1')
			->andWhere('inventoryDocument.purchase = :purchase')
			->andWhere('inventoryDocument.type = :type')
			->andWhere('inventoryDocument.status = :status')
			->setParameter('purchase', $purchase)
			->setParameter('type', $type)
			->setParameter('status', InventoryDocumentStatus::DRAFT)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
	}
}

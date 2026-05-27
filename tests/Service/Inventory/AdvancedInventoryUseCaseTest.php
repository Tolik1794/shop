<?php

namespace App\Tests\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryReason;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Enum\InventoryReasonType;
use App\Enum\ProductKindEnum;
use App\Repository\WarehouseStockRepository;
use App\Repository\InventoryDocumentRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use App\Service\Inventory\CustomerReturnUseCase;
use App\Service\Inventory\InventoryTransferUseCase;
use App\Service\Inventory\OrderShipmentUseCase;
use App\Service\Inventory\PurchaseReceiptUseCase;
use App\Service\Inventory\SupplierReturnUseCase;
use App\Service\Inventory\WriteOffAdjustmentUseCase;
use App\Service\InventoryPostingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AdvancedInventoryUseCaseTest extends TestCase
{
	public function testTransferUseCaseCreatesBalancedDraftWithCostSnapshot(): void
	{
		[$store, $sourceWarehouse, $product] = $this->storeWarehouseAndProduct();
		$destinationWarehouse = (new Warehouse())
			->setStore($store)
			->setName('Destination');
		$reason = $this->reason($store, InventoryReasonType::TRANSFER);
		$sourceStock = (new WarehouseStock())
			->setWarehouse($sourceWarehouse)
			->setProduct($product)
			->setAverageCost('4.2500');
		$entityManager = $this->entityManagerExpectingDraftPersist();
		$postingService = $this->postingService();
		$warehouseStockRepository = $this->warehouseStockRepository($sourceStock);
		$useCase = new InventoryTransferUseCase($entityManager, $postingService, $warehouseStockRepository);

		$document = $useCase->createDraft($store, $product, $sourceWarehouse, $destinationWarehouse, '3.0000', $reason, 'TRF-1');
		$lines = $document->getLines()->toArray();

		self::assertSame(InventoryDocumentType::TRANSFER, $document->getType());
		self::assertSame($reason, $document->getReason());
		self::assertCount(2, $lines);
		self::assertSame(InventoryDirection::OUT, $lines[0]->getDirection());
		self::assertSame($sourceWarehouse, $lines[0]->getWarehouse());
		self::assertSame('4.2500', $lines[0]->getUnitPriceBase());
		self::assertSame(InventoryDirection::IN, $lines[1]->getDirection());
		self::assertSame($destinationWarehouse, $lines[1]->getWarehouse());
		self::assertSame('4.2500', $lines[1]->getUnitPriceBase());
	}

	public function testCustomerReturnUseCaseCreatesAndPostsDraftLinkedToOrderEntry(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$order = (new Order())->setStore($store)->setNumber('SO-1');
		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse);
		$order->addOrderEntry($orderEntry);
		$reason = $this->reason($store, InventoryReasonType::RETURN);
		$entityManager = $this->entityManagerExpectingDraftPersist();
		$postingService = $this->postingServiceExpectingPost();
		$useCase = new CustomerReturnUseCase($entityManager, $postingService, $this->warehouseStockRepository(new WarehouseStock()));

		$document = $useCase->createAndPost($order, $orderEntry, '1.0000', $reason, 'CRN-1');
		$line = $document->getLines()->first();

		self::assertSame(InventoryDocumentType::CUSTOMER_RETURN, $document->getType());
		self::assertSame($order, $document->getOrder());
		self::assertSame(InventoryDirection::IN, $line->getDirection());
		self::assertSame($orderEntry, $line->getOrderEntry());
	}

	public function testOrderShipmentUseCaseCreatesDraftForRemainingOrderQuantities(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$order = (new Order())
			->setStore($store)
			->setNumber('SO-1')
			->setStatus(OrderStatus::PARTIALLY_SHIPPED)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000');
		$firstEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setCanceledQuantity('1.0000')
			->setShippedQuantity('2.0000')
			->setUnitPrice('10.0000')
			->setUnitPriceBase('12.0000')
			->setTotalPrice('50.0000')
			->setTotalPriceBase('60.0000');
		$secondEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('1.0000')
			->setShippedQuantity('1.0000')
			->setUnitPrice('9.0000')
			->setUnitPriceBase('9.0000')
			->setTotalPrice('9.0000')
			->setTotalPriceBase('9.0000');
		$order
			->addOrderEntry($firstEntry)
			->addOrderEntry($secondEntry);
		$useCase = $this->orderShipmentUseCase($this->entityManagerExpectingDraftPersist());

		$document = $useCase->createDraft($order, 'SHP-1');
		$line = $document->getLines()->first();

		self::assertSame(InventoryDocumentType::SALE_SHIPMENT, $document->getType());
		self::assertSame($order, $document->getOrder());
		self::assertSame($order->getCurrency(), $document->getCurrency());
		self::assertSame('1.00000000', $document->getExchangeRateToBase());
		self::assertCount(1, $document->getLines());
		self::assertSame(InventoryDirection::OUT, $line->getDirection());
		self::assertSame($firstEntry, $line->getOrderEntry());
		self::assertSame('2.0000', $line->getQuantity());
		self::assertSame('10.0000', $line->getUnitPrice());
		self::assertSame('12.0000', $line->getUnitPriceBase());
		self::assertSame('20.0000', $line->getTotalPrice());
		self::assertSame('24.0000', $line->getTotalPriceBase());
		self::assertSame('20.0000', $document->getTotalAmount());
		self::assertSame('24.0000', $document->getTotalAmountBase());
	}

	public function testOrderShipmentUseCaseRejectsOrderWithoutRemainingQuantity(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$order = (new Order())
			->setStore($store)
			->setNumber('SO-1')
			->setStatus(OrderStatus::READY_TO_SHIP);
		$order->addOrderEntry((new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setShippedQuantity('2.0000'));
		$useCase = $this->orderShipmentUseCase($this->entityManagerExpectingNoDraftPersist());

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Order has no remaining quantity to ship.');

		$useCase->createDraft($order, 'SHP-1');
	}

	public function testSupplierReturnUseCaseCreatesDraftLinkedToPurchaseEntry(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$purchase = (new Purchase())->setStore($store)->setNumber('PO-1');
		$purchaseEntry = (new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setUnitCostBase('6.5000');
		$purchase->addPurchaseEntry($purchaseEntry);
		$reason = $this->reason($store, InventoryReasonType::RETURN);
		$useCase = new SupplierReturnUseCase($this->entityManagerExpectingDraftPersist(), $this->postingService());

		$document = $useCase->createDraft($purchase, $purchaseEntry, '1.0000', $reason, 'SRN-1');
		$line = $document->getLines()->first();

		self::assertSame(InventoryDocumentType::SUPPLIER_RETURN, $document->getType());
		self::assertSame($purchase, $document->getPurchase());
		self::assertSame(InventoryDirection::OUT, $line->getDirection());
		self::assertSame($purchaseEntry, $line->getPurchaseEntry());
		self::assertSame('6.5000', $line->getUnitPriceBase());
	}

	public function testPurchaseReceiptUseCaseCreatesDraftForRemainingPurchaseQuantities(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber('PO-1')
			->setStatus(PurchaseStatus::PARTIALLY_RECEIVED)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000');
		$firstEntry = (new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setReceivedQuantity('2.0000')
			->setUnitCost('6.5000')
			->setUnitCostBase('7.0000');
		$secondEntry = (new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('1.0000')
			->setReceivedQuantity('1.0000')
			->setUnitCost('9.0000')
			->setUnitCostBase('9.0000');
		$purchase
			->addPurchaseEntry($firstEntry)
			->addPurchaseEntry($secondEntry);
		$useCase = $this->purchaseReceiptUseCase($this->entityManagerExpectingDraftPersist());

		$document = $useCase->createDraft($purchase, 'PRC-1');
		$line = $document->getLines()->first();

		self::assertSame(InventoryDocumentType::PURCHASE_RECEIPT, $document->getType());
		self::assertSame($purchase, $document->getPurchase());
		self::assertSame($purchase->getCurrency(), $document->getCurrency());
		self::assertSame('1.00000000', $document->getExchangeRateToBase());
		self::assertCount(1, $document->getLines());
		self::assertSame(InventoryDirection::IN, $line->getDirection());
		self::assertSame($firstEntry, $line->getPurchaseEntry());
		self::assertSame('3.0000', $line->getQuantity());
		self::assertSame('6.5000', $line->getUnitPrice());
		self::assertSame('7.0000', $line->getUnitPriceBase());
		self::assertSame('19.5000', $line->getTotalPrice());
		self::assertSame('21.0000', $line->getTotalPriceBase());
		self::assertSame('19.5000', $document->getTotalAmount());
		self::assertSame('21.0000', $document->getTotalAmountBase());
	}

	public function testPurchaseReceiptUseCaseRejectsPurchaseWithoutRemainingQuantity(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber('PO-1')
			->setStatus(PurchaseStatus::ORDERED);
		$purchase->addPurchaseEntry((new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setReceivedQuantity('2.0000'));
		$useCase = $this->purchaseReceiptUseCase($this->entityManagerExpectingNoDraftPersist());

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Purchase has no remaining quantity to receive.');

		$useCase->createDraft($purchase, 'PRC-1');
	}

	public function testWriteOffAdjustmentUseCaseCreatesReasonedDrafts(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct();
		$writeOffReason = $this->reason($store, InventoryReasonType::WRITE_OFF);
		$adjustmentReason = $this->reason($store, InventoryReasonType::STOCK_ADJUSTMENT);
		$useCase = new WriteOffAdjustmentUseCase($this->entityManagerExpectingDraftPersist(2), $this->postingService());

		$writeOff = $useCase->createWriteOffDraft($store, $product, $warehouse, '1.0000', $writeOffReason, 'WOF-1');
		$adjustment = $useCase->createStockAdjustmentDraft($store, $adjustmentReason, [[
			'product' => $product,
			'warehouse' => $warehouse,
			'direction' => InventoryDirection::IN,
			'quantity' => '2.0000',
			'unitCost' => '3.0000',
		]], 'ADJ-1');

		self::assertSame(InventoryDocumentType::WRITE_OFF, $writeOff->getType());
		self::assertSame(InventoryDirection::OUT, $writeOff->getLines()->first()->getDirection());
		self::assertSame($writeOffReason, $writeOff->getReason());
		self::assertSame(InventoryDocumentType::STOCK_ADJUSTMENT, $adjustment->getType());
		self::assertSame('3.0000', $adjustment->getLines()->first()->getUnitPriceBase());
		self::assertSame($adjustmentReason, $adjustment->getReason());
	}

	/**
	 * @return array{Store, Warehouse, Product}
	 */
	private function storeWarehouseAndProduct(): array
	{
		$store = (new Store())
			->setName('Store')
			->setSlug('store-' . uniqid())
			->setPhone('+380000000000')
			->setEmail('store-' . uniqid() . '@example.com');
		$warehouse = (new Warehouse())
			->setStore($store)
			->setName('Warehouse');
		$product = (new Product())
			->setStore($store)
			->setProductKind(ProductKindEnum::FINISHED_PRODUCT)
			->setName('Product')
			->setCode('product-' . uniqid());

		return [$store, $warehouse, $product];
	}

	private function reason(Store $store, InventoryReasonType $type): InventoryReason
	{
		return (new InventoryReason())
			->setStore($store)
			->setName($type->value)
			->setType($type);
	}

	private function entityManagerExpectingDraftPersist(int $times = 1): EntityManagerInterface
	{
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects($this->exactly($times))
			->method('persist')
			->with($this->isInstanceOf(InventoryDocument::class));
		$entityManager->expects($this->exactly($times))->method('flush');
		$entityManager->method('wrapInTransaction')
			->willReturnCallback(static fn (callable $callback): mixed => $callback());

		return $entityManager;
	}

	private function entityManagerExpectingNoDraftPersist(): EntityManagerInterface
	{
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects($this->never())->method('persist');
		$entityManager->expects($this->never())->method('flush');
		$entityManager->method('wrapInTransaction')
			->willReturnCallback(static fn (callable $callback): mixed => $callback());

		return $entityManager;
	}

	private function postingService(): InventoryPostingService
	{
		return $this->getMockBuilder(InventoryPostingService::class)
			->disableOriginalConstructor()
			->getMock();
	}

	private function postingServiceExpectingPost(): InventoryPostingService
	{
		$postingService = $this->getMockBuilder(InventoryPostingService::class)
			->disableOriginalConstructor()
			->onlyMethods(['post'])
			->getMock();
		$postingService->expects($this->once())
			->method('post')
			->with($this->isInstanceOf(InventoryDocument::class));

		return $postingService;
	}

	private function warehouseStockRepository(WarehouseStock $warehouseStock): WarehouseStockRepository
	{
		$repository = $this->getMockBuilder(WarehouseStockRepository::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneByProductAndWarehouse'])
			->getMock();
		$repository->method('findOneByProductAndWarehouse')->willReturn($warehouseStock);

		return $repository;
	}

	private function orderShipmentUseCase(EntityManagerInterface $entityManager): OrderShipmentUseCase
	{
		return new OrderShipmentUseCase($entityManager, $this->concurrencyGuard(), $this->inventoryDocumentRepository());
	}

	private function purchaseReceiptUseCase(EntityManagerInterface $entityManager): PurchaseReceiptUseCase
	{
		return new PurchaseReceiptUseCase($entityManager, $this->concurrencyGuard(), $this->inventoryDocumentRepository());
	}

	private function concurrencyGuard(): ConcurrencyGuard
	{
		$concurrencyGuard = $this->createMock(ConcurrencyGuard::class);
		$concurrencyGuard->method('lock')
			->willReturnArgument(0);

		return $concurrencyGuard;
	}

	private function inventoryDocumentRepository(): InventoryDocumentRepository
	{
		$repository = $this->getMockBuilder(InventoryDocumentRepository::class)
			->disableOriginalConstructor()
			->onlyMethods(['hasDraftForOrder', 'hasDraftForPurchase'])
			->getMock();
		$repository->method('hasDraftForOrder')->willReturn(false);
		$repository->method('hasDraftForPurchase')->willReturn(false);

		return $repository;
	}
}

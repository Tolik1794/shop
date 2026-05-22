<?php

namespace App\Tests\Service;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\InventoryReason;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\ProductionOrder;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\InventoryReasonType;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Manager\UserManager;
use App\Repository\WarehouseStockRepository;
use App\Service\BusinessDocumentStatusSynchronizer;
use App\Service\DocumentProgressRecalculator;
use App\Service\InventoryPostingService;
use App\Service\StockReservationService;
use App\Service\WarehouseStockService;
use App\Workflow\History\GenericStatusHistoryRecorder;
use App\Workflow\History\OrderHistoryRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InventoryPostingServiceTest extends TestCase
{
	private EntityManagerInterface&MockObject $entityManager;
	private WarehouseStockService&MockObject $warehouseStockService;
	private WarehouseStockRepository&MockObject $warehouseStockRepository;
	private UserManager&MockObject $userManager;
	private StockReservationService&MockObject $stockReservationService;
	private GenericStatusHistoryRecorder&MockObject $statusHistoryRecorder;
	private OrderHistoryRecorder&MockObject $orderHistoryRecorder;
	private InventoryPostingService $inventoryPostingService;

	protected function setUp(): void
	{
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->warehouseStockService = $this->createMock(WarehouseStockService::class);
		$this->warehouseStockRepository = $this->createMock(WarehouseStockRepository::class);
		$this->userManager = $this->createMock(UserManager::class);
		$this->stockReservationService = $this->createMock(StockReservationService::class);
		$this->statusHistoryRecorder = $this->createMock(GenericStatusHistoryRecorder::class);
		$this->orderHistoryRecorder = $this->createMock(OrderHistoryRecorder::class);
		$this->userManager->method('getCurrentUser')->willReturn(null);
		$this->entityManager->method('persist');
		$this->entityManager->method('flush');
		$this->entityManager->method('wrapInTransaction')
			->willReturnCallback(static fn (callable $callback): mixed => $callback());

		$this->inventoryPostingService = new InventoryPostingService(
			$this->entityManager,
			$this->warehouseStockService,
			$this->userManager,
			new DocumentProgressRecalculator(new BusinessDocumentStatusSynchronizer($this->warehouseStockRepository, $this->statusHistoryRecorder, $this->orderHistoryRecorder)),
			$this->stockReservationService,
			$this->statusHistoryRecorder,
		);
	}

	public function testPostCreatesMovementAndUpdatesAverageCost(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '5.0000', '2.0000');
		$document = $this->document($store)
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '3.0000', '4.0000'));

		$this->warehouseStockService->expects($this->once())
			->method('findOrCreate')
			->with($warehouse, $product)
			->willReturn($warehouseStock);
		$this->statusHistoryRecorder->expects($this->once())
			->method('recordChange')
			->with($document, 'post', 'draft', 'posted', $this->anything());

		$this->inventoryPostingService->post($document);
		$line = $document->getLines()->first();
		$movement = $line->getStockMovements()->first();

		self::assertSame(InventoryDocumentStatus::POSTED, $document->getStatus());
		self::assertSame('8.0000', $warehouseStock->getQuantityOnHand());
		self::assertSame('2.7500', $warehouseStock->getAverageCost());
		self::assertSame($warehouseStock, $line->getWarehouseStock());
		self::assertSame('3.0000', $movement->getQuantityChange());
		self::assertSame('4.0000', $movement->getUnitCost());
		self::assertSame('8.0000', $movement->getBalanceAfter());
	}

	public function testPostBlocksNegativeStock(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '5.0000', '2.0000');
		$document = $this->document($store)
			->addLine($this->line($product, $warehouse, InventoryDirection::OUT, '6.0000'));

		$this->warehouseStockService->method('findOrCreate')->willReturn($warehouseStock);
		$this->statusHistoryRecorder->expects($this->never())->method('recordChange');

		$this->expectException(StockOperationException::class);

		$this->inventoryPostingService->post($document);
	}

	public function testCancelPostedDocumentCreatesPostedReversalAndRestoresStock(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '2.0000', '5.0000');
		$document = $this->document($store)
			->setStatus(InventoryDocumentStatus::POSTED)
			->setPostedAt(new \DateTimeImmutable())
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '2.0000', '5.0000'));

		$this->warehouseStockService->method('findOrCreate')->willReturn($warehouseStock);
		$this->statusHistoryRecorder->expects($this->exactly(2))->method('recordChange');

		$reversal = $this->inventoryPostingService->cancel($document);
		$reversalLine = $reversal?->getLines()->first();

		self::assertInstanceOf(InventoryDocument::class, $reversal);
		self::assertSame(InventoryDocumentStatus::CANCELED, $document->getStatus());
		self::assertSame(InventoryDocumentStatus::POSTED, $reversal->getStatus());
		self::assertSame(InventoryDocumentType::REVERSAL, $reversal->getType());
		self::assertSame($document, $reversal->getReversedDocument());
		self::assertSame(InventoryDirection::OUT, $reversalLine->getDirection());
		self::assertSame('0.0000', $warehouseStock->getQuantityOnHand());
	}

	public function testServiceProductLineDoesNotCreateStockMovement(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::SERVICE);
		$document = $this->document($store)
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '3.0000', '4.0000'));

		$this->warehouseStockService->expects($this->never())->method('findOrCreate');

		$this->inventoryPostingService->post($document);
		$line = $document->getLines()->first();

		self::assertSame(InventoryDocumentStatus::POSTED, $document->getStatus());
		self::assertCount(0, $line->getStockMovements());
		self::assertNull($line->getWarehouseStock());
	}

	public function testDocumentTypeDirectionMismatchIsBlocked(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$document = $this->document($store)
			->setType(InventoryDocumentType::PURCHASE_RECEIPT)
			->addLine($this->line($product, $warehouse, InventoryDirection::OUT, '3.0000', '4.0000'));

		$this->warehouseStockService->expects($this->never())->method('findOrCreate');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Inventory document type "purchase_receipt" requires "in" line direction.');

		$this->inventoryPostingService->post($document);
	}

	public function testPurchaseReceiptUpdatesEntryProgressAndPurchaseStatus(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber('PO-' . uniqid())
			->setStatus(PurchaseStatus::ORDERED);
		$purchaseEntry = (new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setUnitCost('4.0000')
			->setUnitCostBase('4.0000')
			->setTotalCost('20.0000')
			->setTotalCostBase('20.0000');
		$purchase->addPurchaseEntry($purchaseEntry);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '0.0000', '0.0000');
		$document = $this->document($store)
			->setType(InventoryDocumentType::PURCHASE_RECEIPT)
			->setPurchase($purchase)
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '5.0000', '4.0000')
				->setPurchaseEntry($purchaseEntry));

		$this->warehouseStockService->method('findOrCreate')->willReturn($warehouseStock);

		$this->inventoryPostingService->post($document);

		self::assertSame('5.0000', $purchaseEntry->getReceivedQuantity());
		self::assertSame('0.0000', $purchaseEntry->getReturnedQuantity());
		self::assertSame(PurchaseStatus::RECEIVED, $purchase->getStatus());
	}

	public function testOrderShipmentUpdatesEntryProgressAndOrderStatus(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$order = (new Order())
			->setStore($store)
			->setNumber('SO-' . uniqid())
			->setStatus(OrderStatus::CONFIRMED);
		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000')
			->setUnitPriceBase('10.0000')
			->setTotalPrice('20.0000')
			->setTotalPriceBase('20.0000')
			->setProductNameSnapshot('Inventory product')
			->setProductCodeSnapshot('inventory-product')
			->setUnitCodeSnapshot('pc')
			->setUnitNameSnapshot('Piece');
		$order->addOrderEntry($orderEntry);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '2.0000', '6.0000');
		$document = $this->document($store)
			->setType(InventoryDocumentType::SALE_SHIPMENT)
			->setOrder($order)
			->addLine($this->line($product, $warehouse, InventoryDirection::OUT, '2.0000', '10.0000')
				->setOrderEntry($orderEntry));

		$this->warehouseStockService->method('findOrCreate')->willReturn($warehouseStock);
		$this->stockReservationService->expects($this->once())
			->method('completeForOrderEntry')
			->with($orderEntry, '2.0000', false);
		$this->orderHistoryRecorder->expects($this->once())
			->method('recordStatusChanged')
			->with($order, 'sync_progress', 'confirmed', 'shipped', $this->anything());

		$this->inventoryPostingService->post($document);

		self::assertSame('2.0000', $orderEntry->getShippedQuantity());
		self::assertSame('0.0000', $orderEntry->getReturnedQuantity());
		self::assertSame(OrderStatus::SHIPPED, $order->getStatus());
		self::assertSame('6.0000', $document->getLines()->first()->getStockMovements()->first()->getUnitCost());
	}

	public function testCancelPostedSaleShipmentRecordsOrderStatusDriftBackToReadyToShip(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$order = (new Order())
			->setStore($store)
			->setNumber('SO-' . uniqid())
			->setStatus(OrderStatus::CONFIRMED);
		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('2.0000')
			->setUnitPrice('10.0000')
			->setUnitPriceBase('10.0000')
			->setTotalPrice('20.0000')
			->setTotalPriceBase('20.0000')
			->setProductNameSnapshot('Inventory product')
			->setProductCodeSnapshot('inventory-product')
			->setUnitCodeSnapshot('pc')
			->setUnitNameSnapshot('Piece');
		$order->addOrderEntry($orderEntry);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '2.0000', '6.0000');
		$document = $this->document($store)
			->setType(InventoryDocumentType::SALE_SHIPMENT)
			->setOrder($order)
			->addLine($this->line($product, $warehouse, InventoryDirection::OUT, '2.0000', '10.0000')
				->setOrderEntry($orderEntry));
		$historyCalls = [];

		$this->warehouseStockService->method('findOrCreate')->willReturn($warehouseStock);
		$this->warehouseStockRepository->method('findOneByProductAndWarehouse')->willReturn($warehouseStock);
		$this->orderHistoryRecorder->expects($this->exactly(2))
			->method('recordStatusChanged')
			->willReturnCallback(static function (Order $orderArg, string $transitionKey, string $fromStatus, string $toStatus) use ($order, &$historyCalls): void {
				self::assertSame($order, $orderArg);
				self::assertSame('sync_progress', $transitionKey);
				$historyCalls[] = [$fromStatus, $toStatus];
			});

		$this->inventoryPostingService->post($document);
		$this->inventoryPostingService->cancel($document);

		self::assertSame([
			['confirmed', 'shipped'],
			['shipped', 'ready_to_ship'],
		], $historyCalls);
		self::assertSame(OrderStatus::READY_TO_SHIP, $order->getStatus());
		self::assertSame('0.0000', $orderEntry->getShippedQuantity());
		self::assertSame('2.0000', $warehouseStock->getQuantityOnHand());
	}

	public function testDirectReversalPostingIsBlocked(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$document = $this->document($store)
			->setStatus(InventoryDocumentStatus::POSTED)
			->setPostedAt(new \DateTimeImmutable())
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '2.0000', '5.0000'));
		$reversal = $this->inventoryPostingService->createReversal($document);

		$this->warehouseStockService->expects($this->never())->method('findOrCreate');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Reversal inventory documents can only be posted through cancel flow.');

		$this->inventoryPostingService->post($reversal);
	}

	public function testTransferMovesBalancedPairWithSourceCostSnapshot(): void
	{
		[$store, $sourceWarehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$destinationWarehouse = (new Warehouse())
			->setName('Destination warehouse')
			->setStore($store);
		$sourceStock = $this->warehouseStock($sourceWarehouse, $product, '5.0000', '7.0000');
		$destinationStock = $this->warehouseStock($destinationWarehouse, $product, '1.0000', '2.0000');
		$document = $this->document($store)
			->setType(InventoryDocumentType::TRANSFER)
			->setReason($this->reason($store, InventoryReasonType::TRANSFER))
			->addLine($this->line($product, $sourceWarehouse, InventoryDirection::OUT, '2.0000'))
			->addLine($this->line($product, $destinationWarehouse, InventoryDirection::IN, '2.0000'));

		$this->warehouseStockService->method('findOrCreate')
			->willReturnCallback(static fn (Warehouse $warehouseArg, Product $productArg): WarehouseStock => $warehouseArg === $sourceWarehouse ? $sourceStock : $destinationStock);

		$this->inventoryPostingService->post($document);

		$outLine = $document->getLines()->first();
		$inLine = $document->getLines()->last();

		self::assertSame('3.0000', $sourceStock->getQuantityOnHand());
		self::assertSame('3.0000', $destinationStock->getQuantityOnHand());
		self::assertSame('5.3333', $destinationStock->getAverageCost());
		self::assertSame('7.0000', $outLine->getStockMovements()->first()->getUnitCost());
		self::assertSame('7.0000', $inLine->getStockMovements()->first()->getUnitCost());
	}

	public function testTransferRequiresBalancedPair(): void
	{
		[$store, $sourceWarehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$destinationWarehouse = (new Warehouse())
			->setName('Destination warehouse')
			->setStore($store);
		$document = $this->document($store)
			->setType(InventoryDocumentType::TRANSFER)
			->setReason($this->reason($store, InventoryReasonType::TRANSFER))
			->addLine($this->line($product, $sourceWarehouse, InventoryDirection::OUT, '2.0000'))
			->addLine($this->line($product, $destinationWarehouse, InventoryDirection::IN, '1.0000'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Transfer inventory document OUT and IN quantities must match.');

		$this->inventoryPostingService->post($document);
	}

	public function testCustomerReturnQuantityCannotExceedShippedQuantity(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$order = (new Order())
			->setStore($store)
			->setNumber('SO-' . uniqid())
			->setStatus(OrderStatus::SHIPPED);
		$orderEntry = (new OrderEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setShippedQuantity('2.0000')
			->setReturnedQuantity('1.0000');
		$order->addOrderEntry($orderEntry);
		$document = $this->document($store)
			->setType(InventoryDocumentType::CUSTOMER_RETURN)
			->setReason($this->reason($store, InventoryReasonType::RETURN))
			->setOrder($order)
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '2.0000')
				->setOrderEntry($orderEntry));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Customer return quantity cannot exceed shipped quantity that has not already been returned.');

		$this->inventoryPostingService->post($document);
	}

	public function testSupplierReturnQuantityCannotExceedReceivedQuantity(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber('PO-' . uniqid())
			->setStatus(PurchaseStatus::RECEIVED);
		$purchaseEntry = (new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setReceivedQuantity('2.0000')
			->setReturnedQuantity('1.0000');
		$purchase->addPurchaseEntry($purchaseEntry);
		$document = $this->document($store)
			->setType(InventoryDocumentType::SUPPLIER_RETURN)
			->setReason($this->reason($store, InventoryReasonType::RETURN))
			->setPurchase($purchase)
			->addLine($this->line($product, $warehouse, InventoryDirection::OUT, '2.0000')
				->setPurchaseEntry($purchaseEntry));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Supplier return quantity cannot exceed received quantity that has not already been returned.');

		$this->inventoryPostingService->post($document);
	}

	public function testWriteOffRequiresInventoryReason(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$document = $this->document($store)
			->setType(InventoryDocumentType::WRITE_OFF)
			->setReason(null)
			->addLine($this->line($product, $warehouse, InventoryDirection::OUT, '1.0000'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Inventory document type "write_off" requires an inventory reason.');

		$this->inventoryPostingService->post($document);
	}

	public function testStockAdjustmentInLineRequiresExplicitCost(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$document = $this->document($store)
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '1.0000'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Stock adjustment IN lines require an explicit unit cost.');

		$this->inventoryPostingService->post($document);
	}

	public function testCancelPostedPurchaseReceiptDowngradesProgressAndStatus(): void
	{
		[$store, $warehouse, $product] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$purchase = (new Purchase())
			->setStore($store)
			->setNumber('PO-' . uniqid())
			->setStatus(PurchaseStatus::ORDERED);
		$purchaseEntry = (new PurchaseEntry())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setQuantity('5.0000')
			->setUnitCost('4.0000')
			->setUnitCostBase('4.0000')
			->setTotalCost('20.0000')
			->setTotalCostBase('20.0000');
		$purchase->addPurchaseEntry($purchaseEntry);
		$warehouseStock = $this->warehouseStock($warehouse, $product, '0.0000', '0.0000');
		$document = $this->document($store)
			->setType(InventoryDocumentType::PURCHASE_RECEIPT)
			->setPurchase($purchase)
			->addLine($this->line($product, $warehouse, InventoryDirection::IN, '5.0000', '4.0000')
				->setPurchaseEntry($purchaseEntry));

		$this->warehouseStockService->method('findOrCreate')->willReturn($warehouseStock);

		$this->inventoryPostingService->post($document);
		$this->inventoryPostingService->cancel($document);

		self::assertSame('0.0000', $purchaseEntry->getReceivedQuantity());
		self::assertSame(PurchaseStatus::ORDERED, $purchase->getStatus());
		self::assertSame('0.0000', $warehouseStock->getQuantityOnHand());
	}

	public function testProductionDocumentUpdatesCompletedQuantity(): void
	{
		[$store, $warehouse, $output] = $this->storeWarehouseAndProduct(ProductKindEnum::FINISHED_PRODUCT);
		$output->setCanBeManufactured(true);
		$material = (new Product())
			->setStore($store)
			->setProductKind(ProductKindEnum::MATERIAL)
			->setName('Production material')
			->setCode('production-material-' . uniqid())
			->setCanBeSold(false)
			->setCanBePurchased(true)
			->setCanBeManufactured(false);
		$outputStock = $this->warehouseStock($warehouse, $output, '0.0000', '0.0000');
		$materialStock = $this->warehouseStock($warehouse, $material, '10.0000', '2.0000');
		$productionOrder = (new ProductionOrder())
			->setStore($store)
			->setProduct($output)
			->setWarehouse($warehouse)
			->setPlannedQuantity('3.0000');
		$document = $this->document($store)
			->setType(InventoryDocumentType::PRODUCTION)
			->setProductionOrder($productionOrder)
			->addLine($this->line($material, $warehouse, InventoryDirection::OUT, '6.0000'))
			->addLine($this->line($output, $warehouse, InventoryDirection::IN, '3.0000', '4.0000'));

		$this->warehouseStockService->method('findOrCreate')
			->willReturnCallback(static fn (Warehouse $warehouseArg, Product $productArg): WarehouseStock => $productArg === $material ? $materialStock : $outputStock);

		$this->inventoryPostingService->post($document);

		self::assertSame('3.0000', $productionOrder->getCompletedQuantity());
		self::assertSame('4.0000', $outputStock->getAverageCost());
		self::assertSame('4.0000', $materialStock->getQuantityOnHand());
	}

	/**
	 * @return array{Store, Warehouse, Product}
	 */
	private function storeWarehouseAndProduct(ProductKindEnum $productKind): array
	{
		$store = (new Store())
			->setName('Inventory store')
			->setSlug('inventory-store-' . uniqid())
			->setPhone('+380000000000')
			->setEmail('inventory-' . uniqid() . '@example.com');
		$warehouse = (new Warehouse())
			->setName('Inventory warehouse')
			->setStore($store);
		$product = (new Product())
			->setStore($store)
			->setProductKind($productKind)
			->setName('Inventory product')
			->setCode('inventory-product-' . uniqid())
			->setCanBeSold(true)
			->setCanBePurchased(true)
			->setCanBeManufactured(false);

		return [$store, $warehouse, $product];
	}

	private function warehouseStock(Warehouse $warehouse, Product $product, string $quantityOnHand, string $averageCost): WarehouseStock
	{
		return (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($product)
			->setQuantityOnHand($quantityOnHand)
			->setAverageCost($averageCost);
	}

	private function document(Store $store): InventoryDocument
	{
		return (new InventoryDocument())
			->setStore($store)
			->setNumber('INV-' . uniqid())
			->setType(InventoryDocumentType::STOCK_ADJUSTMENT)
			->setReason($this->reason($store, InventoryReasonType::STOCK_ADJUSTMENT));
	}

	private function reason(Store $store, InventoryReasonType $type): InventoryReason
	{
		return (new InventoryReason())
			->setStore($store)
			->setName($type->value . '-' . uniqid())
			->setType($type);
	}

	private function line(
		Product $product,
		Warehouse $warehouse,
		InventoryDirection $direction,
		string $quantity,
		?string $unitPriceBase = null,
	): InventoryDocumentLine {
		return (new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection($direction)
			->setQuantity($quantity)
			->setUnitPriceBase($unitPriceBase);
	}
}

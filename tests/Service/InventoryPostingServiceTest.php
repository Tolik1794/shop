<?php

namespace App\Tests\Service;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Manager\UserManager;
use App\Service\InventoryPostingService;
use App\Service\WarehouseStockService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InventoryPostingServiceTest extends TestCase
{
	private EntityManagerInterface&MockObject $entityManager;
	private WarehouseStockService&MockObject $warehouseStockService;
	private UserManager&MockObject $userManager;
	private InventoryPostingService $inventoryPostingService;

	protected function setUp(): void
	{
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->warehouseStockService = $this->createMock(WarehouseStockService::class);
		$this->userManager = $this->createMock(UserManager::class);
		$this->userManager->method('getCurrentUser')->willReturn(null);
		$this->entityManager->method('persist');
		$this->entityManager->method('flush');

		$this->inventoryPostingService = new InventoryPostingService(
			$this->entityManager,
			$this->warehouseStockService,
			$this->userManager,
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
			->setType(InventoryDocumentType::STOCK_ADJUSTMENT);
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

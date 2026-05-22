<?php

namespace App\Tests\Service\Lifecycle;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\OrderHistory;
use App\Entity\Payment;
use App\Entity\ProductionOrder;
use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\StockMovement;
use App\Entity\StockReservation;
use App\Entity\Store;
use App\Enum\InventoryDocumentStatus;
use App\Repository\StatusHistoryRepository;
use App\Service\Lifecycle\DocumentDeletionPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentDeletionPolicyTest extends TestCase
{
	private StatusHistoryRepository&MockObject $statusHistoryRepository;
	private DocumentDeletionPolicy $policy;

	protected function setUp(): void
	{
		$this->statusHistoryRepository = $this
			->getMockBuilder(StatusHistoryRepository::class)
			->disableOriginalConstructor()
			->onlyMethods(['hasTimelineFor'])
			->getMock();

		$this->policy = new DocumentDeletionPolicy($this->statusHistoryRepository);
	}

	public function testAllowsDraftInventoryDocumentWithoutMovementsOrHistory(): void
	{
		$document = new InventoryDocument();
		$document->addLine(new InventoryDocumentLine());

		self::assertTrue($this->policy->canHardDelete($document));
	}

	public function testBlocksPostedInventoryDocument(): void
	{
		$document = (new InventoryDocument())->setStatus(InventoryDocumentStatus::POSTED);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Only draft inventory documents can be hard deleted.');

		$this->policy->assertCanHardDelete($document);
	}

	public function testBlocksInventoryDocumentWithStatusHistory(): void
	{
		$store = new Store();
		$document = (new InventoryDocument())->setStore($store);
		$this->setEntityId($document, 15);

		$this->statusHistoryRepository
			->expects($this->once())
			->method('hasTimelineFor')
			->with($store, StatusHistoryEntityType::INVENTORY_DOCUMENT, 15)
			->willReturn(true);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Inventory documents with status history cannot be hard deleted.');

		$this->policy->assertCanHardDelete($document);
	}

	public function testAllowsDraftInventoryDocumentLineWithoutMovements(): void
	{
		$line = new InventoryDocumentLine();

		self::assertTrue($this->policy->canHardDelete($line));
	}

	public function testBlocksInventoryDocumentLineWithStockMovement(): void
	{
		$line = new InventoryDocumentLine();
		$line->addStockMovement(new StockMovement());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Inventory document lines with stock movements cannot be hard deleted.');

		$this->policy->assertCanHardDelete($line);
	}

	public function testBlocksProductionOrderWithInventoryDocument(): void
	{
		$productionOrder = new ProductionOrder();
		$productionOrder->addInventoryDocument(new InventoryDocument());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Production orders with inventory documents cannot be hard deleted.');

		$this->policy->assertCanHardDelete($productionOrder);
	}

	public function testProtectedBusinessRecordsAreAppendOnly(): void
	{
		foreach ([
			new Payment(),
			new StockMovement(),
			new StockReservation(),
			new OrderHistory(),
			new StatusHistory(),
		] as $entity) {
			self::assertFalse($this->policy->canHardDelete($entity));
		}
	}

	private function setEntityId(object $entity, int $id): void
	{
		$property = new \ReflectionProperty($entity, 'id');
		$property->setValue($entity, $id);
	}
}

<?php

namespace App\Tests\Workflow\History;

use App\Entity\ProductionOrder;
use App\Entity\Purchase;
use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Entity\InventoryDocument;
use App\Workflow\History\GenericStatusHistoryRecorder;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionResult;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

class GenericStatusHistoryRecorderTest extends TestCase
{
	public function testRecordsPurchaseStatusHistory(): void
	{
		$store = new Store();
		$purchase = (new Purchase())->setStore($store);
		$this->setEntityId($purchase, 42);
		$occurredAt = new DateTimeImmutable('2026-05-21 12:00:00');
		$context = TransitionContext::system(comment: 'Ordered by automation.', occurredAt: $occurredAt);
		$result = new TransitionResult($purchase, 'order', 'draft', 'ordered');

		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager
			->expects(self::once())
			->method('persist')
			->with(self::callback(static function (StatusHistory $history) use ($store, $occurredAt): bool {
				self::assertSame(StatusHistoryEntityType::PURCHASE, $history->getEntityType());
				self::assertSame(42, $history->getEntityId());
				self::assertSame('draft', $history->getOldStatus());
				self::assertSame('ordered', $history->getNewStatus());
				self::assertSame('Ordered by automation.', $history->getComment());
				self::assertSame($occurredAt, $history->getChangedAt());
				self::assertSame($store, $history->getStore());
				self::assertNull($history->getChangedBy());

				return true;
			}));

		$recorder = new GenericStatusHistoryRecorder($entityManager);
		$recorder->record(
			$purchase,
			new TransitionDefinition('order', ['draft'], 'ordered'),
			$context,
			$result,
		);
	}

	public function testRecordsProductionOrderStatusHistory(): void
	{
		$store = new Store();
		$productionOrder = (new ProductionOrder())->setStore($store);
		$this->setEntityId($productionOrder, 77);
		$result = new TransitionResult($productionOrder, 'plan', 'draft', 'planned');

		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager
			->expects(self::once())
			->method('persist')
			->with(self::callback(static function (StatusHistory $history): bool {
				self::assertSame(StatusHistoryEntityType::PRODUCTION_ORDER, $history->getEntityType());
				self::assertSame(77, $history->getEntityId());
				self::assertSame('draft', $history->getOldStatus());
				self::assertSame('planned', $history->getNewStatus());

				return true;
			}));

		$recorder = new GenericStatusHistoryRecorder($entityManager);
		$recorder->record(
			$productionOrder,
			new TransitionDefinition('plan', ['draft'], 'planned'),
			TransitionContext::system(),
			$result,
		);
	}

	public function testRecordsInventoryDocumentStatusHistory(): void
	{
		$store = new Store();
		$document = (new InventoryDocument())->setStore($store);
		$this->setEntityId($document, 105);
		$result = new TransitionResult($document, 'post', 'draft', 'posted');

		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager
			->expects(self::once())
			->method('persist')
			->with(self::callback(static function (StatusHistory $history): bool {
				self::assertSame(StatusHistoryEntityType::INVENTORY_DOCUMENT, $history->getEntityType());
				self::assertSame(105, $history->getEntityId());
				self::assertSame('draft', $history->getOldStatus());
				self::assertSame('posted', $history->getNewStatus());

				return true;
			}));

		$recorder = new GenericStatusHistoryRecorder($entityManager);
		$recorder->record(
			$document,
			new TransitionDefinition('post', ['draft'], 'posted'),
			TransitionContext::system(),
			$result,
		);
	}

	private function setEntityId(object $entity, int $id): void
	{
		$reflection = new ReflectionObject($entity);
		$property = $reflection->getProperty('id');
		$property->setValue($entity, $id);
	}
}

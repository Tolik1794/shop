<?php

namespace App\Service\Lifecycle;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderStatus;
use App\Entity\Purchase;
use App\Entity\PurchaseStatus;
use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\StockMovement;
use App\Entity\StockReservation;
use App\Enum\InventoryDocumentStatus;
use App\Repository\StatusHistoryRepository;
use RuntimeException;

class DocumentDeletionPolicy
{
	public function __construct(
		private readonly StatusHistoryRepository $statusHistoryRepository,
	)
	{
	}

	public function canHardDelete(object $entity): bool
	{
		try {
			$this->assertCanHardDelete($entity);
		} catch (RuntimeException) {
			return false;
		}

		return true;
	}

	public function assertCanHardDelete(object $entity): void
	{
		if ($entity instanceof InventoryDocument) {
			$this->assertInventoryDocumentCanBeHardDeleted($entity);

			return;
		}

		if ($entity instanceof InventoryDocumentLine) {
			$this->assertInventoryDocumentLineCanBeHardDeleted($entity);

			return;
		}

		if ($entity instanceof Order) {
			$this->assertOrderCanBeHardDeleted($entity);

			return;
		}

		if ($entity instanceof Purchase) {
			$this->assertPurchaseCanBeHardDeleted($entity);

			return;
		}

		if ($entity instanceof ProductionOrder) {
			$this->assertProductionOrderCanBeHardDeleted($entity);

			return;
		}

		if (
			$entity instanceof Payment
			|| $entity instanceof StockMovement
			|| $entity instanceof StockReservation
			|| $entity instanceof OrderHistory
			|| $entity instanceof StatusHistory
		) {
			throw new RuntimeException(sprintf('%s is historical business data and cannot be hard deleted.', $entity::class));
		}

		throw new RuntimeException(sprintf('No hard-delete policy is defined for %s.', $entity::class));
	}

	private function assertInventoryDocumentCanBeHardDeleted(InventoryDocument $document): void
	{
		if ($document->getStatus() !== InventoryDocumentStatus::DRAFT) {
			throw new RuntimeException('Only draft inventory documents can be hard deleted.');
		}

		if (!$document->getReversalDocuments()->isEmpty()) {
			throw new RuntimeException('Inventory documents with reversal links cannot be hard deleted.');
		}

		foreach ($document->getLines() as $line) {
			$this->assertInventoryDocumentLineCanBeHardDeleted($line);
		}

		if ($this->hasStatusHistory($document, StatusHistoryEntityType::INVENTORY_DOCUMENT)) {
			throw new RuntimeException('Inventory documents with status history cannot be hard deleted.');
		}
	}

	private function assertInventoryDocumentLineCanBeHardDeleted(InventoryDocumentLine $line): void
	{
		if (!$line->getStockMovements()->isEmpty()) {
			throw new RuntimeException('Inventory document lines with stock movements cannot be hard deleted.');
		}

		$document = $line->getInventoryDocument();

		if ($document instanceof InventoryDocument && $document->getStatus() !== InventoryDocumentStatus::DRAFT) {
			throw new RuntimeException('Lines of posted or canceled inventory documents cannot be hard deleted.');
		}
	}

	private function assertOrderCanBeHardDeleted(Order $order): void
	{
		if ($order->getStatus() !== OrderStatus::DRAFT) {
			throw new RuntimeException('Only draft orders can be hard deleted.');
		}

		if (!$order->getPayments()->isEmpty()) {
			throw new RuntimeException('Orders with payments cannot be hard deleted.');
		}

		if (!$order->getInventoryDocuments()->isEmpty()) {
			throw new RuntimeException('Orders with inventory documents cannot be hard deleted.');
		}

		if (!$order->getHistoryEntries()->isEmpty()) {
			throw new RuntimeException('Orders with history entries cannot be hard deleted.');
		}
	}

	private function assertPurchaseCanBeHardDeleted(Purchase $purchase): void
	{
		if ($purchase->getStatus() !== PurchaseStatus::DRAFT) {
			throw new RuntimeException('Only draft purchases can be hard deleted.');
		}

		if (!$purchase->getPayments()->isEmpty()) {
			throw new RuntimeException('Purchases with payments cannot be hard deleted.');
		}

		if (!$purchase->getInventoryDocuments()->isEmpty()) {
			throw new RuntimeException('Purchases with inventory documents cannot be hard deleted.');
		}

		if ($this->hasStatusHistory($purchase, StatusHistoryEntityType::PURCHASE)) {
			throw new RuntimeException('Purchases with status history cannot be hard deleted.');
		}
	}

	private function assertProductionOrderCanBeHardDeleted(ProductionOrder $productionOrder): void
	{
		if ($productionOrder->getStatus() !== ProductionOrderStatus::DRAFT) {
			throw new RuntimeException('Only draft production orders can be hard deleted.');
		}

		if (!$productionOrder->getInventoryDocuments()->isEmpty()) {
			throw new RuntimeException('Production orders with inventory documents cannot be hard deleted.');
		}

		if ($this->hasStatusHistory($productionOrder, StatusHistoryEntityType::PRODUCTION_ORDER)) {
			throw new RuntimeException('Production orders with status history cannot be hard deleted.');
		}
	}

	private function hasStatusHistory(InventoryDocument|Purchase|ProductionOrder $entity, StatusHistoryEntityType $entityType): bool
	{
		$store = $entity->getStore();
		$id = $entity->getId();

		if ($store === null || $id === null) {
			return false;
		}

		return $this->statusHistoryRepository->hasTimelineFor($store, $entityType, $id);
	}
}

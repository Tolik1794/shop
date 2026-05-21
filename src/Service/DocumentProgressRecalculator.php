<?php

namespace App\Service;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\ProductionOrder;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Workflow\TransitionContext;

class DocumentProgressRecalculator
{
	public function __construct(private readonly BusinessDocumentStatusSynchronizer $statusSynchronizer)
	{
	}

	public function recalculateForInventoryDocument(InventoryDocument $document, ?TransitionContext $context = null): void
	{
		if ($document->getOrder() instanceof Order) {
			$this->recalculateOrder($document->getOrder());
		}

		if ($document->getPurchase() instanceof Purchase) {
			$this->recalculatePurchase($document->getPurchase(), $context);
		}

		if ($document->getProductionOrder() instanceof ProductionOrder) {
			$this->recalculateProductionOrder($document->getProductionOrder());
		}

		$reversedDocument = $document->getReversedDocument();
		if ($reversedDocument instanceof InventoryDocument) {
			$this->recalculateForInventoryDocument($reversedDocument, $context);
		}
	}

	public function recalculateOrder(Order $order): void
	{
		foreach ($order->getOrderEntries() as $entry) {
			$this->recalculateOrderEntry($entry);
		}

		$this->statusSynchronizer->syncOrder($order);
	}

	public function recalculatePurchase(Purchase $purchase, ?TransitionContext $context = null): void
	{
		foreach ($purchase->getPurchaseEntries() as $entry) {
			$this->recalculatePurchaseEntry($entry);
		}

		$this->statusSynchronizer->syncPurchase($purchase, $context);
	}

	public function recalculateProductionOrder(ProductionOrder $productionOrder): void
	{
		$completed = 0.0;
		$product = $productionOrder->getProduct();

		foreach ($productionOrder->getInventoryDocuments() as $document) {
			if (!$this->isProgressSource($document) || $document->getType() !== InventoryDocumentType::PRODUCTION) {
				continue;
			}

			foreach ($document->getLines() as $line) {
				if ($line->getProduct() !== $product || $line->getDirection() !== InventoryDirection::IN) {
					continue;
				}

				$completed += $this->numberValue($line->getQuantity());
			}
		}

		$productionOrder->setCompletedQuantity($this->formatQuantity(max(0, $completed)));
	}

	private function recalculateOrderEntry(OrderEntry $entry): void
	{
		$shipped = 0.0;
		$returned = 0.0;

		foreach ($entry->getInventoryDocumentLines() as $line) {
			$document = $line->getInventoryDocument();
			if (!$this->isProgressSource($document)) {
				continue;
			}

			$quantity = $this->signedQuantity($line);
			$type = $document->getType();

			if ($type === InventoryDocumentType::SALE_SHIPMENT) {
				$shipped += -$quantity;
			}

			if ($type === InventoryDocumentType::CUSTOMER_RETURN) {
				$returned += $quantity;
			}
		}

		$entry
			->setShippedQuantity($this->formatQuantity(max(0, $shipped)))
			->setReturnedQuantity($this->formatQuantity(max(0, $returned)))
			->setCanceledQuantity($entry->getCanceledQuantity() ?? '0.0000');
	}

	private function recalculatePurchaseEntry(PurchaseEntry $entry): void
	{
		$received = 0.0;
		$returned = 0.0;

		foreach ($entry->getInventoryDocumentLines() as $line) {
			$document = $line->getInventoryDocument();
			if (!$this->isProgressSource($document)) {
				continue;
			}

			$quantity = $this->signedQuantity($line);
			$type = $document->getType();

			if ($type === InventoryDocumentType::PURCHASE_RECEIPT) {
				$received += $quantity;
			}

			if ($type === InventoryDocumentType::SUPPLIER_RETURN) {
				$returned += -$quantity;
			}
		}

		$entry
			->setReceivedQuantity($this->formatQuantity(max(0, $received)))
			->setReturnedQuantity($this->formatQuantity(max(0, $returned)));
	}

	private function isProgressSource(?InventoryDocument $document): bool
	{
		return $document instanceof InventoryDocument
			&& $document->getStatus() === InventoryDocumentStatus::POSTED
			&& $document->getType() !== InventoryDocumentType::REVERSAL;
	}

	private function signedQuantity(InventoryDocumentLine $line): float
	{
		$quantity = $this->numberValue($line->getQuantity());

		return $line->getDirection() === InventoryDirection::IN ? $quantity : -$quantity;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatQuantity(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

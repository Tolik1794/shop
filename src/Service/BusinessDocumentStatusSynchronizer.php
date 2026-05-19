<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Purchase;
use App\Entity\PurchaseStatus;
use App\Enum\ProductKindEnum;
use App\Repository\WarehouseStockRepository;

class BusinessDocumentStatusSynchronizer
{
	public function __construct(private readonly WarehouseStockRepository $warehouseStockRepository)
	{
	}

	public function syncOrder(Order $order): void
	{
		if (in_array($order->getStatus(), [
			OrderStatus::DRAFT,
			OrderStatus::CANCELED,
			OrderStatus::DELIVERED,
			OrderStatus::COMPLETED,
		], true)) {
			return;
		}

		$expected = 0.0;
		$shipped = 0.0;
		$returned = 0.0;

		foreach ($order->getOrderEntries() as $entry) {
			$expected += max(0, $this->numberValue($entry->getQuantity()) - $this->numberValue($entry->getCanceledQuantity()));
			$shipped += $this->numberValue($entry->getShippedQuantity());
			$returned += $this->numberValue($entry->getReturnedQuantity());
		}

		if ($expected <= 0) {
			return;
		}

		if ($returned > 0 && $this->isEnough($returned, $expected)) {
			$order->setStatus(OrderStatus::RETURNED);
			return;
		}

		if ($returned > 0) {
			$order->setStatus(OrderStatus::PARTIALLY_RETURNED);
			return;
		}

		if ($this->isEnough($shipped, $expected)) {
			$order->setStatus(OrderStatus::SHIPPED);
			return;
		}

		if ($shipped > 0) {
			$order->setStatus(OrderStatus::PARTIALLY_SHIPPED);
			return;
		}

		$order->setStatus($this->hasAvailableStock($order) ? OrderStatus::READY_TO_SHIP : OrderStatus::AWAITING_STOCK);
	}

	public function syncPurchase(Purchase $purchase): void
	{
		if (in_array($purchase->getStatus(), [
			PurchaseStatus::DRAFT,
			PurchaseStatus::CANCELED,
			PurchaseStatus::COMPLETED,
		], true)) {
			return;
		}

		$expected = 0.0;
		$received = 0.0;
		$returned = 0.0;

		foreach ($purchase->getPurchaseEntries() as $entry) {
			$expected += $this->numberValue($entry->getQuantity());
			$received += $this->numberValue($entry->getReceivedQuantity());
			$returned += $this->numberValue($entry->getReturnedQuantity());
		}

		if ($expected <= 0) {
			return;
		}

		if ($returned > 0 && $received > 0 && $this->isEnough($returned, $received)) {
			$purchase->setStatus(PurchaseStatus::RETURNED);
			return;
		}

		if ($returned > 0) {
			$purchase->setStatus(PurchaseStatus::PARTIALLY_RETURNED);
			return;
		}

		if ($this->isEnough($received, $expected)) {
			$purchase->setStatus(PurchaseStatus::RECEIVED);
			return;
		}

		if ($received > 0) {
			$purchase->setStatus(PurchaseStatus::PARTIALLY_RECEIVED);
			return;
		}

		$purchase->setStatus(PurchaseStatus::ORDERED);
	}

	private function hasAvailableStock(Order $order): bool
	{
		foreach ($order->getOrderEntries() as $entry) {
			if (!$this->entryHasAvailableStock($entry)) {
				return false;
			}
		}

		return true;
	}

	private function entryHasAvailableStock(OrderEntry $entry): bool
	{
		$product = $entry->getProduct();
		$warehouse = $entry->getWarehouse();
		if ($product?->getProductKind() === ProductKindEnum::SERVICE) {
			return true;
		}

		if (!$product || !$warehouse) {
			return false;
		}

		$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);
		if (!$warehouseStock) {
			return false;
		}

		$required = max(
			0,
			$this->numberValue($entry->getQuantity())
			- $this->numberValue($entry->getCanceledQuantity())
			- $this->numberValue($entry->getShippedQuantity()),
		);
		$available = $this->numberValue($warehouseStock->getQuantityOnHand()) - $this->numberValue($warehouseStock->getReservedQuantity());

		return $this->isEnough($available, $required);
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function isEnough(float $actual, float $expected): bool
	{
		return $actual + 0.00005 >= $expected;
	}
}

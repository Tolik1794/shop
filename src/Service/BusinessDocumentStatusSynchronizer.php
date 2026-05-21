<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Purchase;
use App\Entity\PurchaseStatus;
use App\Enum\ProductKindEnum;
use App\Repository\WarehouseStockRepository;
use App\Workflow\History\GenericStatusHistoryRecorder;
use App\Workflow\History\OrderHistoryRecorder;
use App\Workflow\TransitionContext;

class BusinessDocumentStatusSynchronizer
{
	public function __construct(
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly GenericStatusHistoryRecorder $statusHistoryRecorder,
		private readonly OrderHistoryRecorder $orderHistoryRecorder,
	)
	{
	}

	public function syncOrder(Order $order, ?TransitionContext $context = null): void
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
			$this->setOrderStatus($order, OrderStatus::RETURNED, $context);
			return;
		}

		if ($returned > 0) {
			$this->setOrderStatus($order, OrderStatus::PARTIALLY_RETURNED, $context);
			return;
		}

		if ($this->isEnough($shipped, $expected)) {
			$this->setOrderStatus($order, OrderStatus::SHIPPED, $context);
			return;
		}

		if ($shipped > 0) {
			$this->setOrderStatus($order, OrderStatus::PARTIALLY_SHIPPED, $context);
			return;
		}

		$this->setOrderStatus(
			$order,
			$this->hasAvailableStock($order) ? OrderStatus::READY_TO_SHIP : OrderStatus::AWAITING_STOCK,
			$context,
		);
	}

	public function syncPurchase(Purchase $purchase, ?TransitionContext $context = null): void
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
			$this->setPurchaseStatus($purchase, PurchaseStatus::RETURNED, $context);
			return;
		}

		if ($returned > 0) {
			$this->setPurchaseStatus($purchase, PurchaseStatus::PARTIALLY_RETURNED, $context);
			return;
		}

		if ($this->isEnough($received, $expected)) {
			$this->setPurchaseStatus($purchase, PurchaseStatus::RECEIVED, $context);
			return;
		}

		if ($received > 0) {
			$this->setPurchaseStatus($purchase, PurchaseStatus::PARTIALLY_RECEIVED, $context);
			return;
		}

		$this->setPurchaseStatus($purchase, PurchaseStatus::ORDERED, $context);
	}

	private function setPurchaseStatus(Purchase $purchase, PurchaseStatus $status, ?TransitionContext $context): void
	{
		$fromStatus = $purchase->getStatus();
		if ($fromStatus === $status) {
			return;
		}

		$purchase->setStatus($status);

		if ($context instanceof TransitionContext) {
			$this->statusHistoryRecorder->recordChange($purchase, 'sync_progress', $fromStatus->value, $status->value, $context);
		}
	}

	private function setOrderStatus(Order $order, OrderStatus $status, ?TransitionContext $context): void
	{
		$fromStatus = $order->getStatus();
		if ($fromStatus === $status) {
			return;
		}

		$order->setStatus($status);

		if ($context instanceof TransitionContext) {
			$this->orderHistoryRecorder->recordStatusChanged($order, 'sync_progress', $fromStatus->value, $status->value, $context);
		}
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

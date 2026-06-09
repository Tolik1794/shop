<?php

namespace App\Manager;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderEntryFulfillmentSource;
use App\Entity\OrderHistory;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\Store;
use App\Entity\User\User;
use App\Exception\StockOperationException;
use App\Enum\ProductKindEnum;
use App\Repository\OrderHistoryRepository;
use App\Repository\OrderRepository;
use App\Repository\WarehouseStockRepository;
use App\Service\BusinessDocumentStatusSynchronizer;
use App\Service\Concurrency\ConcurrencyGuard;
use App\Service\Discount\OrderEntryDiscountService;
use App\Service\ExchangeRateResolver;
use App\Service\Order\OrderEntryPricingService;
use App\Service\Order\OrderEntrySnapshotter;
use App\Service\Order\OrderBatchPricingService;
use App\Service\OrderCalculator;
use App\Service\Production\ProductionDemandService;
use App\Service\StockReservationService;
use App\Workflow\History\OrderHistoryChangeSetBuilder;
use App\Workflow\History\OrderHistoryRecorder;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class OrderManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ExchangeRateResolver $exchangeRateResolver,
		private readonly OrderEntrySnapshotter $orderEntrySnapshotter,
		private readonly OrderEntryPricingService $orderEntryPricingService,
		private readonly OrderBatchPricingService $orderBatchPricingService,
		private readonly OrderEntryDiscountService $orderEntryDiscountService,
		private readonly OrderCalculator $orderCalculator,
		private readonly StatusTransitionService $statusTransitionService,
		private readonly OrderHistoryChangeSetBuilder $orderHistoryChangeSetBuilder,
		private readonly OrderHistoryRecorder $orderHistoryRecorder,
		private readonly OrderHistoryRepository $orderHistoryRepository,
		private readonly UserManager $userManager,
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly StockReservationService $stockReservationService,
		private readonly ProductionDemandService $productionDemandService,
		private readonly BusinessDocumentStatusSynchronizer $businessDocumentStatusSynchronizer,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	public function createDraft(Store $store): Order
	{
		return new Order()
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setNumber($this->getRepository()->getNextNumber($store));
	}

	public function saveOrder(Order $order, iterable $removedEntries = []): void
	{
		$isNew = $order->getId() === null;
		$actor = $this->currentActor();
		$this->prepareOrder($order);
		$this->orderBatchPricingService->normalizeOrderEntries($order);
		foreach ($removedEntries as $removedEntry) {
			if ($removedEntry instanceof OrderEntry) {
				$this->entityManager->remove($removedEntry);
			}
		}
		foreach ($order->getOrderEntries() as $orderEntry) {
			$orderEntry->setOrder($order);
			$this->prepareEntry($orderEntry);
			$this->entityManager->persist($orderEntry);
		}
		$this->recalculate($order);

		if ($isNew) {
			$this->orderHistoryRecorder->recordCreated($order, $actor);
		} else {
			$orderChanges = $this->orderHistoryChangeSetBuilder->buildOrderChanges($order);
			$this->orderHistoryRecorder->recordUpdated($order, $orderChanges, $actor);

			if (array_key_exists('customer', $orderChanges)) {
				$this->orderHistoryRecorder->recordCustomerChanged($order, [
					'customer' => $orderChanges['customer'],
				], $actor);
			}
		}

		foreach ($order->getOrderEntries() as $orderEntry) {
			if ($orderEntry->getId() === null) {
				$this->orderHistoryRecorder->recordEntryAdded(
					$order,
					$this->orderHistoryChangeSetBuilder->buildEntryPayload($orderEntry),
					$actor
				);

				continue;
			}

			$this->orderHistoryRecorder->recordEntryUpdated(
				$order,
				$this->orderHistoryChangeSetBuilder->buildEntryChanges($orderEntry),
				$actor,
				$orderEntry->getId(),
			);
		}

		foreach ($removedEntries as $removedEntry) {
			if ($removedEntry instanceof OrderEntry) {
				$this->orderHistoryRecorder->recordEntryRemoved(
					$order,
					$this->orderHistoryChangeSetBuilder->buildEntryPayload($removedEntry),
					$actor,
					$removedEntry->getId(),
				);
			}
		}

		$this->save($order);
	}

	public function confirm(Order $order): void
	{
		foreach ($order->getOrderEntries() as $orderEntry) {
			$this->productionDemandService->assertCanPlanDemand($orderEntry);
		}

		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$context = $this->transitionContext();
			$this->statusTransitionService->apply($order, 'confirm', $context);
			$this->saveOrder($order);
			foreach ($order->getOrderEntries() as $orderEntry) {
				$this->productionDemandService->ensurePlannedDemand($orderEntry, $context);
			}
			$this->reserveStockForOrder($order);
			$this->businessDocumentStatusSynchronizer->syncOrder($order, $context);
			$this->entityManager->flush();
		});
	}

	public function cancel(Order $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			if ($order->getStatus() === OrderStatus::CANCELED) {
				return;
			}

			$context = $this->transitionContext();
			$this->statusTransitionService->apply($order, 'cancel', $context);
			foreach ($order->getOrderEntries() as $orderEntry) {
				$this->productionDemandService->cancelPlannedDemand($orderEntry, $context);
			}
			$this->releaseActiveReservations($order);
			$this->saveStatusOnlyOrder($order);
		});
	}

	public function returnToDraft(Order $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$this->statusTransitionService->apply($order, 'return_to_draft', $this->transitionContext());
			foreach ($order->getOrderEntries() as $orderEntry) {
				$this->productionDemandService->cancelPlannedDemand($orderEntry, $this->transitionContext());
			}
			$this->releaseActiveReservations($order);
			$this->saveOrder($order);
		});
	}

	public function canRollbackStatus(Order $order): bool
	{
		return $this->resolveRollbackTargetStatus($order) instanceof OrderStatus;
	}

	public function getRollbackTargetStatus(Order $order): ?OrderStatus
	{
		return $this->resolveRollbackTargetStatus($order);
	}

	public function rollbackStatus(Order $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$targetHistoryEntry = $this->rollbackTargetHistoryEntry($order);
			$targetStatus = $this->rollbackTargetStatus($targetHistoryEntry);

			if (!$targetStatus instanceof OrderStatus) {
				throw new RuntimeException('Order has no previous status to rollback to.');
			}

			$fromStatus = $order->getStatus();
			if (!$fromStatus instanceof OrderStatus) {
				throw new RuntimeException('Order status is not set.');
			}

			$context = $this->transitionContext([
				'transition' => 'rollback_status',
				'rolled_back_history_id' => $targetHistoryEntry->getId(),
			]);
			$this->statusTransitionService->applyTransition(
				$order,
				new TransitionDefinition(
					key: 'rollback_status',
					fromStatuses: [$fromStatus->value],
					toStatus: $targetStatus->value,
					historyEventKey: 'order.status_changed',
				),
				$context,
			);

			if ($fromStatus === OrderStatus::CANCELED && $targetStatus !== OrderStatus::CANCELED) {
				$order
					->setCanceledAt(null)
					->setCanceledBy(null);
			}

			if ($targetStatus === OrderStatus::DRAFT) {
				foreach ($order->getOrderEntries() as $orderEntry) {
					$this->productionDemandService->cancelPlannedDemand($orderEntry, $context);
				}
				$this->releaseActiveReservations($order);
			}

			$this->saveStatusOnlyOrder($order);

			if ($this->shouldRefreshReservationsAfterRollback($targetStatus)) {
				$this->reserveStockForOrder($order);
				$this->entityManager->flush();
			}
		});
	}

	public function markDelivered(Order $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$this->statusTransitionService->apply($order, 'mark_delivered', $this->transitionContext());
			$this->saveStatusOnlyOrder($order);
		});
	}

	public function complete(Order $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$this->statusTransitionService->apply($order, 'complete', $this->transitionContext());
			$this->saveStatusOnlyOrder($order);
		});
	}

	/**
	 * Refuses (cancels) part or all of the not-yet-shipped quantity of a single position. The refused
	 * quantity is recorded on the entry's canceledQuantity, the matching stock reservation is released,
	 * and the aggregate order status is re-synchronized. Other positions are untouched, so an order can
	 * ship the rest while one position is declined.
	 */
	public function refuseEntryRemaining(Order $order, OrderEntry $orderEntry, string $quantity): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order, $orderEntry, $quantity): void {
			$this->concurrencyGuard->lock($order);

			if ($orderEntry->getOrder() !== $order) {
				throw new RuntimeException('Order entry does not belong to the order.');
			}

			$refuse = $this->numberValue($quantity);
			if ($refuse <= 0.00005) {
				throw new RuntimeException('Refused quantity must be greater than zero.');
			}

			$remaining = max(
				0.0,
				$this->numberValue($orderEntry->getQuantity())
				- $this->numberValue($orderEntry->getCanceledQuantity())
				- $this->numberValue($orderEntry->getShippedQuantity()),
			);

			if ($refuse > $remaining + 0.00005) {
				throw new RuntimeException('Cannot refuse more than the remaining quantity of a position.');
			}

			$previousCanceled = $this->numberValue($orderEntry->getCanceledQuantity());

			// Free the reserved stock for the refused part so it becomes available to other orders.
			$this->stockReservationService->releaseForOrderEntry($orderEntry, $this->formatQuantity($refuse), false);

			$orderEntry->setCanceledQuantity($this->formatQuantity($previousCanceled + $refuse));

			$context = $this->transitionContext(['transition' => 'refuse_entry_remaining']);
			$this->productionDemandService->adjustPlannedDemand($orderEntry, $context);

			// syncOrder already excludes canceledQuantity from the expected quantity, so the aggregate
			// status is recomputed correctly without an explicit workflow transition.
			$this->businessDocumentStatusSynchronizer->syncOrder($order, $context);

			$this->orderHistoryRecorder->recordEntryUpdated(
				$order,
				['canceledQuantity' => [
					'from' => $this->formatQuantity($previousCanceled),
					'to' => $orderEntry->getCanceledQuantity(),
				]],
				$this->currentActor(),
				$orderEntry->getId(),
			);

			$this->entityManager->flush();
		});
	}

	/**
	 * Re-evaluates a waiting order after stock arrived elsewhere (purchase receipt, production, return, …):
	 * reserves the newly available stock and re-synchronizes the aggregate status, advancing
	 * `awaiting_stock` → `ready_to_ship` when every position is now covered. No-op for other statuses.
	 *
	 * Locks in the same Order→Stock order as {@see confirm()} (reservation locks stock internally), so it is
	 * deadlock-safe when run after the inventory posting transaction has committed.
	 */
	public function refreshAvailability(Order $order): void
	{
		if ($order->getStatus() !== OrderStatus::AWAITING_STOCK) {
			return;
		}

		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);

			// Re-check under the lock: another action may have moved the order in the meantime.
			if ($order->getStatus() !== OrderStatus::AWAITING_STOCK) {
				return;
			}

			$this->reserveStockForOrder($order);
			$context = $this->transitionContext(['transition' => 'replenish_availability']);
			$this->businessDocumentStatusSynchronizer->syncOrder($order, $context);
			$this->entityManager->flush();
		});
	}

	public function recalculate(Order $order): void
	{
		foreach ($order->getOrderEntries() as $orderEntry) {
			$this->prepareEntry($orderEntry);
		}

		$this->orderCalculator->apply($order);
	}

	public function getRepository(): OrderRepository
	{
		return $this->entityManager->getRepository(Order::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function prepareOrder(Order $order): void
	{
		$currency = $order->getCurrency();
		$store = $order->getStore();
		$baseCurrency = $store?->getBaseCurrency();

		if (!$currency || !$store || !$baseCurrency) {
			return;
		}

		$order->setExchangeRateToBase($this->exchangeRateResolver->resolve($currency, $baseCurrency, $store));
	}

	private function prepareEntry(OrderEntry $orderEntry): void
	{
		$product = $orderEntry->getProduct();
		$order = $orderEntry->getOrder();

		if (!$product instanceof Product || !$order instanceof Order) {
			return;
		}

		$this->orderEntrySnapshotter->snapshot($orderEntry);
		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			$orderEntry->setFulfillmentSource(OrderEntryFulfillmentSource::SERVICE);
		}
		$this->orderBatchPricingService->applyBatchPrice($orderEntry);
		$this->orderEntryPricingService->initializeUnitPrice($orderEntry, $order);
		$this->orderEntryDiscountService->apply($orderEntry, $order);
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function transitionContext(array $payload = []): TransitionContext
	{
		$actor = $this->currentActor();

		return $actor instanceof User
			? TransitionContext::manual($actor, $payload)
			: TransitionContext::system($payload);
	}

	private function rollbackTargetHistoryEntry(Order $order): OrderHistory
	{
		$historyEntry = $this->resolveRollbackTargetHistoryEntry($order);

		if (!$historyEntry instanceof OrderHistory) {
			throw new RuntimeException('Order has no previous status to rollback to.');
		}

		return $historyEntry;
	}

	private function resolveRollbackTargetStatus(Order $order): ?OrderStatus
	{
		$historyEntry = $this->resolveRollbackTargetHistoryEntry($order);

		if (!$historyEntry instanceof OrderHistory) {
			return null;
		}

		return $this->rollbackTargetStatus($historyEntry);
	}

	private function resolveRollbackTargetHistoryEntry(Order $order): ?OrderHistory
	{
		$currentStatus = $order->getStatus();
		if (!$currentStatus instanceof OrderStatus) {
			return null;
		}

		return $this->orderHistoryRepository->findLatestRollbackableStatusChangeToStatus($order, $currentStatus->value);
	}

	private function rollbackTargetStatus(OrderHistory $historyEntry): ?OrderStatus
	{
		$targetStatus = $historyEntry->getChanges()['status']['from'] ?? null;

		if (!is_string($targetStatus)) {
			return null;
		}

		return OrderStatus::tryFrom($targetStatus);
	}

	private function shouldRefreshReservationsAfterRollback(OrderStatus $status): bool
	{
		return in_array($status, [
			OrderStatus::CONFIRMED,
			OrderStatus::AWAITING_STOCK,
			OrderStatus::READY_TO_SHIP,
		], true);
	}

	private function saveStatusOnlyOrder(Order $order): void
	{
		// Status-only actions must not re-run order-entry preparation. Batch availability may have
		// legitimately changed after shipment or reservation consumption, while the action only
		// writes workflow state/history and related reservation side effects.
		$this->save($order);
	}

	private function reserveStockForOrder(Order $order): void
	{
		foreach ($order->getOrderEntries() as $orderEntry) {
			$product = $orderEntry->getProduct();
			$warehouse = $orderEntry->getWarehouse();

			if (
				!$product instanceof Product
				|| $warehouse === null
				|| $orderEntry->getFulfillmentSource() !== OrderEntryFulfillmentSource::STOCK
			) {
				continue;
			}

			$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);
			if ($warehouseStock === null) {
				if ($order->getStore()?->isAllowBackorders()) {
					continue;
				}

				throw new StockOperationException('Warehouse stock row was not found for order entry.');
			}

			$this->stockReservationService->reserveForOrderEntry($orderEntry, $warehouseStock);
		}
	}

	private function releaseActiveReservations(Order $order): void
	{
		foreach ($order->getOrderEntries() as $orderEntry) {
			foreach ($orderEntry->getStockReservations() as $reservation) {
				if (!$reservation instanceof StockReservation || $reservation->getStatus() !== StockReservationStatus::ACTIVE) {
					continue;
				}

				$this->stockReservationService->release($reservation);
			}
		}
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

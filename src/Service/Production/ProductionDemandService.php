<?php

namespace App\Service\Production;

use App\Entity\OrderEntry;
use App\Entity\OrderEntryFulfillmentSource;
use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderStatus;
use App\Entity\ProductionRecipe;
use App\Entity\StockReservation;
use App\Entity\StockReservationStatus;
use App\Entity\WarehouseStock;
use App\Enum\ActiveStatusEnum;
use App\Exception\ProductionDemandException;
use App\Repository\ProductionOrderRepository;
use App\Repository\ProductionRecipeRepository;
use App\Repository\WarehouseStockRepository;
use App\Service\BusinessDocumentStatusSynchronizer;
use App\Service\Concurrency\ConcurrencyGuard;
use App\Service\StockReservationService;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionContext;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class ProductionDemandService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ProductionOrderFactory $productionOrderFactory,
		private readonly ProductionRecipeRepository $productionRecipeRepository,
		private readonly ProductionOrderRepository $productionOrderRepository,
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly StockReservationService $stockReservationService,
		private readonly BusinessDocumentStatusSynchronizer $statusSynchronizer,
		private readonly StatusTransitionService $statusTransitionService,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	public function ensurePlannedDemand(OrderEntry $entry, TransitionContext $context): ?ProductionOrder
	{
		if ($entry->getFulfillmentSource() !== OrderEntryFulfillmentSource::PRODUCTION) {
			return null;
		}

		$activeOrder = $this->productionOrderRepository->findActiveForOrderEntry($entry);
		if ($activeOrder instanceof ProductionOrder) {
			return $activeOrder;
		}

		$quantity = $this->uncoveredQuantity($entry);
		if ($quantity <= 0.00005) {
			return null;
		}

		return $this->createPlannedDemand($entry, $this->formatQuantity($quantity), $context);
	}

	public function assertCanPlanDemand(OrderEntry $entry): void
	{
		if ($entry->getFulfillmentSource() !== OrderEntryFulfillmentSource::PRODUCTION) {
			return;
		}

		$product = $entry->getProduct();
		if (!$product || !$product->isCanBeManufactured()) {
			throw new ProductionDemandException(
				'Production order position product must be manufacturable.',
				'production_demand.product_not_manufacturable',
			);
		}

		if (!$entry->getWarehouse()) {
			throw new ProductionDemandException(
				'Select a production output warehouse for every production position.',
				'production_demand.output_warehouse_required',
			);
		}

		$recipe = $this->productionRecipeRepository->findDefaultForProduct($product);
		if (!$recipe instanceof ProductionRecipe || $recipe->getStatus() !== ActiveStatusEnum::ACTIVE) {
			throw new ProductionDemandException(
				sprintf('Product "%s" requires an active default production recipe.', $product->getName()),
				'production_demand.active_default_recipe_required',
				['%product%' => (string) $product->getName()],
			);
		}
	}

	public function adjustPlannedDemand(OrderEntry $entry, TransitionContext $context): void
	{
		if ($entry->getFulfillmentSource() !== OrderEntryFulfillmentSource::PRODUCTION) {
			return;
		}

		$activeOrder = $this->productionOrderRepository->findActiveForOrderEntry($entry);
		if ($activeOrder instanceof ProductionOrder && in_array($activeOrder->getStatus(), [
			ProductionOrderStatus::DRAFT,
			ProductionOrderStatus::PLANNED,
		], true)) {
			$this->concurrencyGuard->lock($activeOrder);
			$this->statusTransitionService->apply($activeOrder, 'cancel', $context);
			$this->entityManager->persist($activeOrder);
			$this->entityManager->flush();
			$activeOrder = null;
		}

		if (!$activeOrder instanceof ProductionOrder) {
			$this->ensurePlannedDemand($entry, $context);
		}
	}

	public function cancelPlannedDemand(OrderEntry $entry, TransitionContext $context): void
	{
		$activeOrder = $this->productionOrderRepository->findActiveForOrderEntry($entry);
		if (!$activeOrder instanceof ProductionOrder || !in_array($activeOrder->getStatus(), [
			ProductionOrderStatus::DRAFT,
			ProductionOrderStatus::PLANNED,
		], true)) {
			return;
		}

		$this->concurrencyGuard->lock($activeOrder);
		$this->statusTransitionService->apply($activeOrder, 'cancel', $context);
		$this->entityManager->persist($activeOrder);
		$this->entityManager->flush();
	}

	public function fulfillCompletedProduction(int $productionOrderId): void
	{
		$productionOrder = $this->productionOrderRepository->find($productionOrderId);
		if (!$productionOrder instanceof ProductionOrder || $productionOrder->getStatus() !== ProductionOrderStatus::COMPLETED) {
			return;
		}

		$entry = $productionOrder->getSourceOrderEntry();
		$warehouse = $productionOrder->getWarehouse();
		$product = $productionOrder->getProduct();
		$order = $entry?->getOrder();

		if (!$entry instanceof OrderEntry || !$order || !$warehouse || !$product) {
			return;
		}

		$this->entityManager->wrapInTransaction(function () use ($productionOrder, $entry, $order, $warehouse, $product): void {
			$this->concurrencyGuard->lockAll([$order, $productionOrder]);
			$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);
			if ($warehouseStock instanceof WarehouseStock) {
				$uncovered = $this->uncoveredQuantity($entry);
				$available = max(0, $this->numberValue($warehouseStock->getQuantityOnHand()) - $this->numberValue($warehouseStock->getReservedQuantity()));
				$quantityToReserve = min($uncovered, $available, $this->numberValue($productionOrder->getCompletedQuantity()));

				if ($quantityToReserve > 0.00005) {
					$this->stockReservationService->reserve($entry, $warehouseStock, $this->formatQuantity($quantityToReserve));
				}
			}

			$context = TransitionContext::system(['transition' => 'linked_production_completed']);
			$this->statusSynchronizer->syncOrder($order, $context);
			$this->entityManager->persist($order);
			$this->ensurePlannedDemand($entry, $context);
			$this->entityManager->flush();
		});
	}

	private function createPlannedDemand(OrderEntry $entry, string $quantity, TransitionContext $context): ProductionOrder
	{
		$this->assertCanPlanDemand($entry);
		$recipe = $this->productionRecipeRepository->findDefaultForProduct($entry->getProduct());

		$productionOrder = $this->productionOrderFactory->createFromRecipe(
			$recipe,
			$quantity,
			$entry->getWarehouse(),
			$context->actor,
		)->setSourceOrderEntry($entry);

		$this->entityManager->persist($productionOrder);
		$this->entityManager->flush();
		$this->statusTransitionService->apply($productionOrder, 'plan', $context);
		$this->entityManager->flush();

		return $productionOrder;
	}

	private function uncoveredQuantity(OrderEntry $entry): float
	{
		return max(
			0,
			$this->numberValue($entry->getQuantity())
			- $this->numberValue($entry->getCanceledQuantity())
			- $this->numberValue($entry->getShippedQuantity())
			- $this->activeReservedQuantity($entry),
		);
	}

	private function activeReservedQuantity(OrderEntry $entry): float
	{
		$quantity = 0.0;
		foreach ($entry->getStockReservations() as $reservation) {
			if ($reservation instanceof StockReservation && $reservation->getStatus() === StockReservationStatus::ACTIVE) {
				$quantity += $this->numberValue($reservation->getQuantity());
			}
		}

		return $quantity;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatQuantity(float $quantity): string
	{
		return number_format($quantity, 4, '.', '');
	}
}

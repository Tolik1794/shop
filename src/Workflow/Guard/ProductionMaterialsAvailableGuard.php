<?php

namespace App\Workflow\Guard;

use App\Entity\ProductionOrder;
use App\Exception\StockOperationException;
use App\Repository\WarehouseStockRepository;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class ProductionMaterialsAvailableGuard implements TransitionGuardInterface
{
	public function __construct(private readonly WarehouseStockRepository $warehouseStockRepository)
	{
	}

	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof ProductionOrder) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', ProductionOrder::class, $subject::class));
		}

		$warehouse = $subject->getWarehouse();
		if (!$warehouse) {
			throw new TransitionNotAllowedException('Production warehouse is required before reserving materials.');
		}

		foreach ($subject->getMaterials() as $material) {
			$product = $material->getMaterial();
			if (!$product) {
				throw new TransitionNotAllowedException('Production material product is required.');
			}

			$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);
			if (!$warehouseStock) {
				throw new StockOperationException(sprintf('Material "%s" has no stock in selected warehouse.', $product->getName()));
			}

			$available = (float) $warehouseStock->getQuantityOnHand() - (float) $warehouseStock->getReservedQuantity();
			if ($available + 0.00005 < (float) $material->getPlannedQuantity()) {
				throw new StockOperationException(sprintf('Not enough available stock for material "%s".', $product->getName()));
			}
		}
	}
}

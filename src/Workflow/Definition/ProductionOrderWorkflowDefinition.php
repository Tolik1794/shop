<?php

namespace App\Workflow\Definition;

use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderStatus;
use App\Workflow\Action\MarkProductionOrderCanceledAction;
use App\Workflow\Action\MarkProductionOrderCompletedAction;
use App\Workflow\Action\MarkProductionOrderStartedAction;
use App\Workflow\Exception\UnknownTransitionException;
use App\Workflow\Guard\ProductionHasMaterialsGuard;
use App\Workflow\Guard\ProductionMaterialsAvailableGuard;
use App\Workflow\Guard\ProductionWarehouseRequiredGuard;
use App\Workflow\History\GenericStatusHistoryRecorder;
use App\Workflow\HistoryRecorderInterface;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowDefinitionInterface;
use App\Workflow\WorkflowSubjectInterface;

class ProductionOrderWorkflowDefinition implements WorkflowDefinitionInterface
{
	/** @var array<string, TransitionDefinition> */
	private array $transitions;

	public function __construct(
		ProductionHasMaterialsGuard $productionHasMaterialsGuard,
		ProductionWarehouseRequiredGuard $productionWarehouseRequiredGuard,
		ProductionMaterialsAvailableGuard $productionMaterialsAvailableGuard,
		MarkProductionOrderStartedAction $markStartedAction,
		MarkProductionOrderCompletedAction $markCompletedAction,
		MarkProductionOrderCanceledAction $markCanceledAction,
		private readonly GenericStatusHistoryRecorder $historyRecorder,
	)
	{
		$this->transitions = [
			'plan' => new TransitionDefinition(
				key: 'plan',
				fromStatuses: [ProductionOrderStatus::DRAFT->value],
				toStatus: ProductionOrderStatus::PLANNED->value,
				guards: [$productionHasMaterialsGuard],
				historyEventKey: 'production_order.status_changed',
			),
			'reserve_materials' => new TransitionDefinition(
				key: 'reserve_materials',
				fromStatuses: [ProductionOrderStatus::PLANNED->value],
				toStatus: ProductionOrderStatus::MATERIALS_RESERVED->value,
				guards: [
					$productionHasMaterialsGuard,
					$productionWarehouseRequiredGuard,
					$productionMaterialsAvailableGuard,
				],
				historyEventKey: 'production_order.status_changed',
			),
			'start' => new TransitionDefinition(
				key: 'start',
				fromStatuses: [ProductionOrderStatus::MATERIALS_RESERVED->value],
				toStatus: ProductionOrderStatus::IN_PROGRESS->value,
				afterActions: [$markStartedAction],
				historyEventKey: 'production_order.status_changed',
			),
			'complete' => new TransitionDefinition(
				key: 'complete',
				fromStatuses: [ProductionOrderStatus::IN_PROGRESS->value],
				toStatus: ProductionOrderStatus::COMPLETED->value,
				afterActions: [$markCompletedAction],
				historyEventKey: 'production_order.status_changed',
			),
			'cancel' => new TransitionDefinition(
				key: 'cancel',
				fromStatuses: [
					ProductionOrderStatus::DRAFT->value,
					ProductionOrderStatus::PLANNED->value,
					ProductionOrderStatus::MATERIALS_RESERVED->value,
					ProductionOrderStatus::IN_PROGRESS->value,
				],
				toStatus: ProductionOrderStatus::CANCELED->value,
				afterActions: [$markCanceledAction],
				historyEventKey: 'production_order.status_changed',
			),
		];
	}

	public function supports(WorkflowSubjectInterface $subject): bool
	{
		return $subject instanceof ProductionOrder;
	}

	public function getTransition(string $key): TransitionDefinition
	{
		return $this->transitions[$key] ?? throw new UnknownTransitionException(sprintf(
			'Unknown production order transition "%s".',
			$key,
		));
	}

	public function getHistoryRecorder(): HistoryRecorderInterface
	{
		return $this->historyRecorder;
	}
}

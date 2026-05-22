<?php

namespace App\Workflow\Definition;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Workflow\Action\MarkOrderCanceledAction;
use App\Workflow\Exception\UnknownTransitionException;
use App\Workflow\Guard\OrderHasEntriesGuard;
use App\Workflow\History\OrderHistoryRecorder;
use App\Workflow\HistoryRecorderInterface;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowDefinitionInterface;
use App\Workflow\WorkflowSubjectInterface;

/**
 * Declares the currently supported order status transitions.
 */
class OrderWorkflowDefinition implements WorkflowDefinitionInterface
{
	/** @var array<string, TransitionDefinition> */
	private array $transitions;

	public function __construct(
		OrderHasEntriesGuard $orderHasEntriesGuard,
		MarkOrderCanceledAction $markOrderCanceledAction,
		private readonly OrderHistoryRecorder $historyRecorder,
	)
	{
		$this->transitions = [
			'confirm' => new TransitionDefinition(
				key: 'confirm',
				fromStatuses: [OrderStatus::DRAFT->value],
				toStatus: OrderStatus::CONFIRMED->value,
				guards: [$orderHasEntriesGuard],
				historyEventKey: 'order.status_changed',
			),
			'return_to_draft' => new TransitionDefinition(
				key: 'return_to_draft',
				fromStatuses: [
					OrderStatus::CONFIRMED->value,
					OrderStatus::AWAITING_STOCK->value,
					OrderStatus::READY_TO_SHIP->value,
				],
				toStatus: OrderStatus::DRAFT->value,
				historyEventKey: 'order.status_changed',
			),
			'mark_delivered' => new TransitionDefinition(
				key: 'mark_delivered',
				fromStatuses: [OrderStatus::SHIPPED->value],
				toStatus: OrderStatus::DELIVERED->value,
				historyEventKey: 'order.status_changed',
			),
			'complete' => new TransitionDefinition(
				key: 'complete',
				fromStatuses: [OrderStatus::DELIVERED->value],
				toStatus: OrderStatus::COMPLETED->value,
				historyEventKey: 'order.status_changed',
			),
			'cancel' => new TransitionDefinition(
				key: 'cancel',
				fromStatuses: array_values(array_filter(
					array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases()),
					static fn (string $status): bool => !in_array($status, [
						OrderStatus::CANCELED->value,
						OrderStatus::COMPLETED->value,
					], true),
				)),
				toStatus: OrderStatus::CANCELED->value,
				afterActions: [$markOrderCanceledAction],
				historyEventKey: 'order.status_changed',
			),
		];
	}

	public function supports(WorkflowSubjectInterface $subject): bool
	{
		return $subject instanceof Order;
	}

	public function getTransition(string $key): TransitionDefinition
	{
		return $this->transitions[$key] ?? throw new UnknownTransitionException(sprintf(
			'Unknown order transition "%s".',
			$key,
		));
	}

	public function getHistoryRecorder(): HistoryRecorderInterface
	{
		return $this->historyRecorder;
	}
}

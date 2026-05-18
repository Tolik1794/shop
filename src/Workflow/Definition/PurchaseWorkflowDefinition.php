<?php

namespace App\Workflow\Definition;

use App\Entity\Purchase;
use App\Entity\PurchaseStatus;
use App\Workflow\Action\MarkPurchaseCanceledAction;
use App\Workflow\Exception\UnknownTransitionException;
use App\Workflow\Guard\PurchaseHasEntriesGuard;
use App\Workflow\History\NullHistoryRecorder;
use App\Workflow\HistoryRecorderInterface;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowDefinitionInterface;
use App\Workflow\WorkflowSubjectInterface;

/**
 * Declares the purchase transitions available before receipt-driven sync exists.
 */
class PurchaseWorkflowDefinition implements WorkflowDefinitionInterface
{
	/** @var array<string, TransitionDefinition> */
	private array $transitions;

	public function __construct(
		PurchaseHasEntriesGuard $purchaseHasEntriesGuard,
		MarkPurchaseCanceledAction $markPurchaseCanceledAction,
		private readonly NullHistoryRecorder $historyRecorder,
	)
	{
		$this->transitions = [
			'order' => new TransitionDefinition(
				key: 'order',
				fromStatuses: [PurchaseStatus::DRAFT->value],
				toStatus: PurchaseStatus::ORDERED->value,
				guards: [$purchaseHasEntriesGuard],
				historyEventKey: 'purchase.status_changed',
			),
			'return_to_draft' => new TransitionDefinition(
				key: 'return_to_draft',
				fromStatuses: [PurchaseStatus::ORDERED->value],
				toStatus: PurchaseStatus::DRAFT->value,
				historyEventKey: 'purchase.status_changed',
			),
			'cancel' => new TransitionDefinition(
				key: 'cancel',
				fromStatuses: [
					PurchaseStatus::DRAFT->value,
					PurchaseStatus::ORDERED->value,
				],
				toStatus: PurchaseStatus::CANCELED->value,
				afterActions: [$markPurchaseCanceledAction],
				historyEventKey: 'purchase.status_changed',
			),
		];
	}

	public function supports(WorkflowSubjectInterface $subject): bool
	{
		return $subject instanceof Purchase;
	}

	public function getTransition(string $key): TransitionDefinition
	{
		return $this->transitions[$key] ?? throw new UnknownTransitionException(sprintf(
			'Unknown purchase transition "%s".',
			$key,
		));
	}

	public function getHistoryRecorder(): HistoryRecorderInterface
	{
		return $this->historyRecorder;
	}
}

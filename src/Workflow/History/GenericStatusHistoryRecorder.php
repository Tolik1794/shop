<?php

namespace App\Workflow\History;

use App\Entity\InventoryDocument;
use App\Entity\ProductionOrder;
use App\Entity\Purchase;
use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Workflow\HistoryRecorderInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionResult;
use App\Workflow\WorkflowSubjectInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Records generic append-only status changes for non-order business documents.
 */
class GenericStatusHistoryRecorder implements HistoryRecorderInterface
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function record(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		TransitionResult $result,
	): void
	{
		$entityId = $this->resolveEntityId($subject);

		$history = (new StatusHistory())
			->setEntityType($this->resolveEntityType($subject))
			->setEntityId($entityId)
			->setOldStatus($result->fromStatus)
			->setNewStatus($result->toStatus)
			->setComment($context->comment)
			->setChangedAt($context->occurredAt)
			->setChangedBy($context->actor)
			->setStore($this->resolveStore($subject));

		$this->entityManager->persist($history);
	}

	public function recordChange(
		WorkflowSubjectInterface $subject,
		string $transitionKey,
		string $fromStatus,
		string $toStatus,
		TransitionContext $context,
	): void
	{
		$this->record(
			$subject,
			new TransitionDefinition($transitionKey, [$fromStatus], $toStatus),
			$context,
			new TransitionResult($subject, $transitionKey, $fromStatus, $toStatus),
		);
	}

	private function resolveEntityType(WorkflowSubjectInterface $subject): StatusHistoryEntityType
	{
		return match (true) {
			$subject instanceof Purchase => StatusHistoryEntityType::PURCHASE,
			$subject instanceof InventoryDocument => StatusHistoryEntityType::INVENTORY_DOCUMENT,
			$subject instanceof ProductionOrder => StatusHistoryEntityType::PRODUCTION_ORDER,
			default => throw new LogicException(sprintf('Unsupported status history subject "%s".', $subject::class)),
		};
	}

	private function resolveEntityId(WorkflowSubjectInterface $subject): int
	{
		$entityId = match (true) {
			$subject instanceof Purchase,
			$subject instanceof InventoryDocument,
			$subject instanceof ProductionOrder => $subject->getId(),
			default => null,
		};

		if (!is_int($entityId)) {
			throw new LogicException(sprintf('Cannot record status history for unsaved "%s".', $subject::class));
		}

		return $entityId;
	}

	private function resolveStore(WorkflowSubjectInterface $subject): Store
	{
		$store = match (true) {
			$subject instanceof Purchase,
			$subject instanceof InventoryDocument,
			$subject instanceof ProductionOrder => $subject->getStore(),
			default => null,
		};

		if (!$store instanceof Store) {
			throw new LogicException(sprintf('Cannot record status history without store for "%s".', $subject::class));
		}

		return $store;
	}
}

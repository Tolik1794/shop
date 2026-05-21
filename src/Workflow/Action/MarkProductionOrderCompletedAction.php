<?php

namespace App\Workflow\Action;

use App\Entity\ProductionOrder;
use App\Workflow\TransitionActionInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class MarkProductionOrderCompletedAction implements TransitionActionInterface
{
	public function execute(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof ProductionOrder) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', ProductionOrder::class, $subject::class));
		}

		$subject
			->setCompletedAt($context->occurredAt)
			->setCompletedBy($context->actor);
	}
}

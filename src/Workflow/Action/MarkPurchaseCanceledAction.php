<?php

namespace App\Workflow\Action;

use App\Entity\Purchase;
use App\Workflow\TransitionActionInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class MarkPurchaseCanceledAction implements TransitionActionInterface
{
	public function execute(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof Purchase) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', Purchase::class, $subject::class));
		}

		$subject->setCanceledAt($context->occurredAt);
		$subject->setCanceledBy($context->actor);
	}
}

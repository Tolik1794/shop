<?php

namespace App\Workflow\Action;

use App\Entity\Order;
use App\Workflow\TransitionActionInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class MarkOrderCanceledAction implements TransitionActionInterface
{
	public function execute(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof Order) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', Order::class, $subject::class));
		}

		$subject->setCanceledAt($context->occurredAt);
	}
}

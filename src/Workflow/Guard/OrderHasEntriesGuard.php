<?php

namespace App\Workflow\Guard;

use App\Entity\Order;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class OrderHasEntriesGuard implements TransitionGuardInterface
{
	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof Order) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', Order::class, $subject::class));
		}

		if ($subject->getOrderEntries()->isEmpty()) {
			throw new TransitionNotAllowedException('Order must have at least one entry.');
		}
	}
}

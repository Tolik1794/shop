<?php

namespace App\Workflow\Guard;

use App\Entity\Purchase;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class PurchaseHasEntriesGuard implements TransitionGuardInterface
{
	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof Purchase) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', Purchase::class, $subject::class));
		}

		if ($subject->getPurchaseEntries()->isEmpty()) {
			throw new TransitionNotAllowedException('Purchase must have at least one entry.');
		}
	}
}

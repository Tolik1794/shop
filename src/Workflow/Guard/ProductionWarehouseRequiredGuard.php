<?php

namespace App\Workflow\Guard;

use App\Entity\ProductionOrder;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class ProductionWarehouseRequiredGuard implements TransitionGuardInterface
{
	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof ProductionOrder) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', ProductionOrder::class, $subject::class));
		}

		if (!$subject->getWarehouse()) {
			throw new TransitionNotAllowedException('Production warehouse is required.');
		}
	}
}

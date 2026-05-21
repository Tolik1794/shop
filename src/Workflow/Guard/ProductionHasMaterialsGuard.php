<?php

namespace App\Workflow\Guard;

use App\Entity\ProductionOrder;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class ProductionHasMaterialsGuard implements TransitionGuardInterface
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

		if ((float) $subject->getPlannedQuantity() <= 0) {
			throw new TransitionNotAllowedException('Production order planned quantity must be greater than zero.');
		}

		if ($subject->getMaterials()->isEmpty()) {
			throw new TransitionNotAllowedException('Production order must have at least one material.');
		}

		foreach ($subject->getMaterials() as $material) {
			if (!$material->getMaterial() || (float) $material->getPlannedQuantity() <= 0) {
				throw new TransitionNotAllowedException('Production order materials must have product and positive quantity.');
			}
		}
	}
}

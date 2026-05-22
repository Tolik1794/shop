<?php

namespace App\Workflow\Guard;

use App\Entity\Order;
use App\Entity\Purchase;
use App\Enum\PaymentStatusEnum;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class DocumentFullyPaidGuard implements TransitionGuardInterface
{
	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof Order && !$subject instanceof Purchase) {
			throw new LogicException(sprintf('Expected "%s" or "%s", got "%s".', Order::class, Purchase::class, $subject::class));
		}

		if (in_array($subject->getPaymentStatus(), [PaymentStatusEnum::PAID, PaymentStatusEnum::OVERPAID], true)) {
			return;
		}

		throw new TransitionNotAllowedException('Document must be fully paid before completion.');
	}
}

<?php

namespace App\Workflow\Action;

use App\Entity\Order;
use App\Service\Inventory\CanceledOrderCustomerReturnDraftService;
use App\Workflow\TransitionActionInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\WorkflowSubjectInterface;
use LogicException;

class CreateCustomerReturnDraftOnOrderCancelAction implements TransitionActionInterface
{
	public function __construct(
		private readonly CanceledOrderCustomerReturnDraftService $customerReturnDraftService,
	)
	{
	}

	public function execute(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		if (!$subject instanceof Order) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', Order::class, $subject::class));
		}

		$this->customerReturnDraftService->createDraftIfNeeded($subject);
	}
}

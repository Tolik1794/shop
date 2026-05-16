<?php

namespace App\Workflow\Event;

use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionResult;
use Symfony\Contracts\EventDispatcher\Event;

class StatusTransitionAppliedEvent extends Event
{
	public function __construct(
		public readonly TransitionDefinition $transition,
		public readonly TransitionContext $context,
		public readonly TransitionResult $result,
	)
	{
	}
}

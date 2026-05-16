<?php

namespace App\Workflow;

class TransitionResult
{
	public function __construct(
		public readonly WorkflowSubjectInterface $entity,
		public readonly string $transitionKey,
		public readonly string $fromStatus,
		public readonly string $toStatus,
	)
	{
	}
}

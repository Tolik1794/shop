<?php

namespace App\Workflow;

interface TransitionGuardInterface
{
	/**
	 * Validates whether a transition may continue without mutating the subject.
	 */
	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void;
}

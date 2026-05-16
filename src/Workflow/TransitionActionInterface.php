<?php

namespace App\Workflow;

interface TransitionActionInterface
{
	/**
	 * Executes side effects attached to a transition before or after the status write.
	 */
	public function execute(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void;
}

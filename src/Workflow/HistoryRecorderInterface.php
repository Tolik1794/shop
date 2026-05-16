<?php

namespace App\Workflow;

interface HistoryRecorderInterface
{
	/**
	 * Persists or forwards the already completed transition history event.
	 */
	public function record(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		TransitionResult $result,
	): void;
}

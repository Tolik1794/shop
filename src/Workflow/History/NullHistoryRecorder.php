<?php

namespace App\Workflow\History;

use App\Workflow\HistoryRecorderInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionResult;
use App\Workflow\WorkflowSubjectInterface;

/**
 * Temporary no-op recorder used until persistent workflow history is implemented.
 */
class NullHistoryRecorder implements HistoryRecorderInterface
{
	public function record(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		TransitionResult $result,
	): void
	{
	}
}

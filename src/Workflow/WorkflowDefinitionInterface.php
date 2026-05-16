<?php

namespace App\Workflow;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.workflow_definition')]
interface WorkflowDefinitionInterface
{
	/**
	 * Returns whether this definition owns the workflow for the given subject.
	 */
	public function supports(WorkflowSubjectInterface $subject): bool;

	/**
	 * Returns one named business transition handled by this workflow.
	 */
	public function getTransition(string $key): TransitionDefinition;

	/**
	 * Returns the recorder responsible for this workflow's transition history.
	 */
	public function getHistoryRecorder(): HistoryRecorderInterface;
}

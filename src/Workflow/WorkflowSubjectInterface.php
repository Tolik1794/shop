<?php

namespace App\Workflow;

interface WorkflowSubjectInterface
{
	/**
	 * Returns the stable workflow identifier used by definitions and diagnostics.
	 */
	public function getWorkflowKey(): string;

	/**
	 * Returns the current status as the normalized workflow value.
	 */
	public function getStatusValue(): string;

	/**
	 * Applies the normalized workflow status value to the underlying subject.
	 */
	public function setStatusValue(string $status): void;
}

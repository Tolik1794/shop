<?php

namespace App\Workflow;

use App\Workflow\Exception\UnsupportedWorkflowSubjectException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class WorkflowRegistry
{
	/**
	 * @param iterable<WorkflowDefinitionInterface> $definitions
	 */
	public function __construct(
		#[AutowireIterator('app.workflow_definition')]
		private readonly iterable $definitions,
	)
	{
	}

	/**
	 * Resolves the single workflow definition that supports the subject.
	 */
	public function getFor(WorkflowSubjectInterface $subject): WorkflowDefinitionInterface
	{
		foreach ($this->definitions as $definition) {
			if ($definition->supports($subject)) {
				return $definition;
			}
		}

		throw new UnsupportedWorkflowSubjectException(sprintf(
			'No workflow definition registered for "%s".',
			$subject::class,
		));
	}
}

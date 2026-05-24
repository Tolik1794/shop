<?php

namespace App\Workflow;

use App\Workflow\Event\StatusTransitionAppliedEvent;
use App\Workflow\Exception\TransitionNotAllowedException;
use App\Workflow\Exception\WorkflowException;

readonly class StatusTransitionService
{
	public function __construct(
		private WorkflowRegistry      $workflowRegistry,
		private DomainEventDispatcher $domainEventDispatcher,
	)
	{
	}

	/**
	 * Applies one named business transition and returns the completed change.
	 */
	public function apply(
		WorkflowSubjectInterface $subject,
		string $transitionKey,
		TransitionContext $context,
	): TransitionResult
	{
		[$definition, $transition, $fromStatus] = $this->resolve($subject, $transitionKey);

		return $this->applyResolved($definition, $subject, $transition, $context, $fromStatus);
	}

	/**
	 * Applies a transition built at runtime while keeping the workflow history and events centralized.
	 */
	public function applyTransition(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): TransitionResult
	{
		$definition = $this->workflowRegistry->getFor($subject);

		return $this->applyResolved($definition, $subject, $transition, $context, $subject->getStatusValue());
	}

	/**
	 * Checks the same transition rules as apply() without mutating the subject.
	 */
	public function can(
		WorkflowSubjectInterface $subject,
		string $transitionKey,
		TransitionContext $context,
	): bool
	{
		try {
			[, $transition, $fromStatus] = $this->resolve($subject, $transitionKey);
			$this->assertAllowed($subject, $transition, $context, $fromStatus);
		} catch (WorkflowException) {
			return false;
		}

		return true;
	}

	/**
	 * @return array{WorkflowDefinitionInterface, TransitionDefinition, string}
	 */
	private function resolve(WorkflowSubjectInterface $subject, string $transitionKey): array
	{
		$definition = $this->workflowRegistry->getFor($subject);
		$transition = $definition->getTransition($transitionKey);

		return [$definition, $transition, $subject->getStatusValue()];
	}

	private function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		string $fromStatus,
	): void
	{
		if (!$transition->allowsFrom($fromStatus)) {
			throw new TransitionNotAllowedException(sprintf(
				'Transition "%s" is not allowed from status "%s".',
				$transition->key,
				$fromStatus,
			));
		}

		foreach ($transition->guards as $guard) {
			$guard->assertAllowed($subject, $transition, $context);
		}
	}

	private function applyResolved(
		WorkflowDefinitionInterface $definition,
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		string $fromStatus,
	): TransitionResult
	{
		$this->assertAllowed($subject, $transition, $context, $fromStatus);

		foreach ($transition->beforeActions as $beforeAction) {
			$beforeAction->execute($subject, $transition, $context);
		}

		$subject->setStatusValue($transition->toStatus);

		foreach ($transition->afterActions as $afterAction) {
			$afterAction->execute($subject, $transition, $context);
		}

		$result = new TransitionResult(
			entity: $subject,
			transitionKey: $transition->key,
			fromStatus: $fromStatus,
			toStatus: $transition->toStatus,
		);

		$definition->getHistoryRecorder()->record($subject, $transition, $context, $result);
		$this->domainEventDispatcher->dispatch(new StatusTransitionAppliedEvent($transition, $context, $result));

		return $result;
	}
}

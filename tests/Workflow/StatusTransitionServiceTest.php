<?php

namespace App\Tests\Workflow;

use App\Workflow\DomainEventDispatcher;
use App\Workflow\HistoryRecorderInterface;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionActionInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionGuardInterface;
use App\Workflow\TransitionResult;
use App\Workflow\WorkflowDefinitionInterface;
use App\Workflow\WorkflowRegistry;
use App\Workflow\WorkflowSubjectInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class StatusTransitionServiceTest extends TestCase
{
	public function testApplyRunsPipelineAndReturnsTransitionResult(): void
	{
		$subject = new StatusTransitionTestSubject();
		$guard = new StatusTransitionTestGuard();
		$beforeAction = new StatusTransitionTestAction();
		$afterAction = new StatusTransitionTestAction();
		$historyRecorder = new StatusTransitionTestHistoryRecorder();
		$transition = new TransitionDefinition(
			key: 'confirm',
			fromStatuses: ['draft'],
			toStatus: 'confirmed',
			guards: [$guard],
			beforeActions: [$beforeAction],
			afterActions: [$afterAction],
		);
		$service = $this->createService(new StatusTransitionTestDefinition($transition, $historyRecorder));

		$result = $service->apply($subject, 'confirm', TransitionContext::system());

		self::assertSame('confirmed', $subject->getStatusValue());
		self::assertSame('confirm', $result->transitionKey);
		self::assertSame('draft', $result->fromStatus);
		self::assertSame('confirmed', $result->toStatus);
		self::assertTrue($guard->called);
		self::assertTrue($beforeAction->called);
		self::assertTrue($afterAction->called);
		self::assertSame($result, $historyRecorder->result);
	}

	public function testCanChecksWorkflowWithoutMutatingSubject(): void
	{
		$subject = new StatusTransitionTestSubject();
		$historyRecorder = new StatusTransitionTestHistoryRecorder();
		$transition = new TransitionDefinition(
			key: 'confirm',
			fromStatuses: ['draft'],
			toStatus: 'confirmed',
		);
		$service = $this->createService(new StatusTransitionTestDefinition($transition, $historyRecorder));

		self::assertTrue($service->can($subject, 'confirm', TransitionContext::system()));
		self::assertSame('draft', $subject->getStatusValue());
		self::assertNull($historyRecorder->result);
	}

	private function createService(WorkflowDefinitionInterface $definition): StatusTransitionService
	{
		return new StatusTransitionService(
			new WorkflowRegistry([$definition]),
			new DomainEventDispatcher(new EventDispatcher()),
		);
	}
}

class StatusTransitionTestSubject implements WorkflowSubjectInterface
{
	private string $status = 'draft';

	public function getWorkflowKey(): string
	{
		return 'test';
	}

	public function getStatusValue(): string
	{
		return $this->status;
	}

	public function setStatusValue(string $status): void
	{
		$this->status = $status;
	}
}

class StatusTransitionTestDefinition implements WorkflowDefinitionInterface
{
	public function __construct(
		private readonly TransitionDefinition $transition,
		private readonly HistoryRecorderInterface $historyRecorder,
	)
	{
	}

	public function supports(WorkflowSubjectInterface $subject): bool
	{
		return $subject instanceof StatusTransitionTestSubject;
	}

	public function getTransition(string $key): TransitionDefinition
	{
		return $this->transition;
	}

	public function getHistoryRecorder(): HistoryRecorderInterface
	{
		return $this->historyRecorder;
	}
}

class StatusTransitionTestGuard implements TransitionGuardInterface
{
	public bool $called = false;

	public function assertAllowed(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		$this->called = true;
	}
}

class StatusTransitionTestAction implements TransitionActionInterface
{
	public bool $called = false;

	public function execute(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
	): void
	{
		$this->called = true;
	}
}

class StatusTransitionTestHistoryRecorder implements HistoryRecorderInterface
{
	public ?TransitionResult $result = null;

	public function record(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		TransitionResult $result,
	): void
	{
		$this->result = $result;
	}
}

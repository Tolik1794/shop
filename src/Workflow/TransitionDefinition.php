<?php

namespace App\Workflow;

class TransitionDefinition
{
	/**
	 * @param string[] $fromStatuses
	 * @param TransitionGuardInterface[] $guards
	 * @param TransitionActionInterface[] $beforeActions
	 * @param TransitionActionInterface[] $afterActions
	 */
	public function __construct(
		public readonly string $key,
		public readonly array $fromStatuses,
		public readonly string $toStatus,
		public readonly array $guards = [],
		public readonly array $beforeActions = [],
		public readonly array $afterActions = [],
		public readonly string $historyEventKey = 'status_changed',
	)
	{
	}

	public function allowsFrom(string $status): bool
	{
		return in_array($status, $this->fromStatuses, true);
	}
}

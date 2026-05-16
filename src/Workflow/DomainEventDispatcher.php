<?php

namespace App\Workflow;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DomainEventDispatcher
{
	public function __construct(
		private readonly EventDispatcherInterface $eventDispatcher,
	)
	{
	}

	public function dispatch(Event $event): void
	{
		$this->eventDispatcher->dispatch($event);
	}
}

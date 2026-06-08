<?php

namespace App\EventSubscriber;

use App\Service\Order\AwaitingStockReplenishmentService;
use App\Service\Order\StockReplenishmentQueue;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Drains the stock replenishment queue after the response is sent, i.e. once the request transaction has
 * committed. Running here (not inside the inventory posting transaction) keeps order locks acquired in the
 * normal Order→Stock order and avoids deadlocks with order confirmation.
 */
final class StockReplenishmentSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private readonly StockReplenishmentQueue $queue,
		private readonly AwaitingStockReplenishmentService $replenishmentService,
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::TERMINATE => 'onKernelTerminate',
		];
	}

	public function onKernelTerminate(TerminateEvent $event): void
	{
		foreach ($this->queue->drain() as $storeId => $productIds) {
			$this->replenishmentService->replenish($storeId, $productIds);
		}
	}
}

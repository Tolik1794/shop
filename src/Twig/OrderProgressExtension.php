<?php

namespace App\Twig;

use App\Entity\OrderEntry;
use App\Service\Order\OrderEntryProgress;
use App\Service\Order\OrderEntryProgressCalculator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OrderProgressExtension extends AbstractExtension
{
	public function __construct(private readonly OrderEntryProgressCalculator $orderEntryProgressCalculator)
	{
	}

	public function getFunctions(): array
	{
		return [
			new TwigFunction('order_entry_progress', [$this, 'orderEntryProgress']),
		];
	}

	public function orderEntryProgress(OrderEntry $orderEntry): OrderEntryProgress
	{
		return $this->orderEntryProgressCalculator->calculate($orderEntry);
	}
}

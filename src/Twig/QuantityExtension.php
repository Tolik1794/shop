<?php

namespace App\Twig;

use App\Entity\Product;
use App\Entity\Unit;
use App\Service\Quantity\QuantityFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class QuantityExtension extends AbstractExtension
{
	public function __construct(private readonly QuantityFormatter $quantityFormatter)
	{
	}

	public function getFilters(): array
	{
		return [
			new TwigFilter('quantity', [$this, 'formatQuantity']),
		];
	}

	public function formatQuantity(mixed $quantity, Product|Unit|null $source = null): string
	{
		return $this->quantityFormatter->format($quantity, $source);
	}
}

<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
	public function getFilters(): array
	{
		return [
			new TwigFilter('money', [$this, 'formatMoney']),
		];
	}

	public function formatMoney(mixed $value): string
	{
		if ($value === null || $value === '') {
			return '0.00';
		}

		return number_format((float) str_replace(',', '.', (string) $value), 2, '.', '');
	}
}

<?php

namespace App\Twig;

use App\Enum\ActiveStatusEnum;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ActiveStatusExtension extends AbstractExtension
{
	public function getFilters()
	{
		return [
			new TwigFilter('active_status_class', [$this, 'getClass']),
		];
	}

	public function getClass(ActiveStatusEnum $status): string
	{
		return match ($status) {
			ActiveStatusEnum::ACTIVE => 'bg-success',
			ActiveStatusEnum::INACTIVE => 'bg-secondary',
			default => 'bg-danger',
		};
	}
}
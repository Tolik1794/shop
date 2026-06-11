<?php

namespace App\Dto\Tax;

use App\Entity\LegalEntity;
use DateTimeImmutable;

final readonly class LimitStatusDto
{
	public function __construct(
		public LegalEntity $legalEntity,
		public int $year,
		public string $incomeTotal,
		public ?string $limit,
		public ?float $percent,
		public ?string $remaining,
		public string $avgDaily90,
		public ?string $forecastAnnual,
		public ?DateTimeImmutable $forecastLimitDate,
	) {}

	public function getAlertLevel(): string
	{
		$pct = $this->percent ?? 0.0;

		return match(true) {
			$pct >= 100.0 => 'exceeded',
			$pct >= 95.0  => 'critical',
			$pct >= 85.0  => 'warning',
			$pct >= 70.0  => 'notice',
			default       => 'ok',
		};
	}

	public function getProgressBarClass(): string
	{
		return match($this->getAlertLevel()) {
			'exceeded', 'critical' => 'bg-danger',
			'warning'              => 'bg-warning',
			'notice'               => 'bg-info',
			default                => 'bg-success',
		};
	}

	public function isThreshold70(): bool
	{
		return ($this->percent ?? 0.0) >= 70.0;
	}

	public function isThreshold85(): bool
	{
		return ($this->percent ?? 0.0) >= 85.0;
	}

	public function isThreshold95(): bool
	{
		return ($this->percent ?? 0.0) >= 95.0;
	}

	public function isExceeded(): bool
	{
		return ($this->percent ?? 0.0) >= 100.0;
	}

	public function getPercentClamped(): float
	{
		return min(100.0, $this->percent ?? 0.0);
	}
}

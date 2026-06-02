<?php

namespace App\Service\Discount;

final readonly class DiscountCostBasis
{
	public function __construct(
		private ?string $unitCostBase,
		private string $deliveryCostBase = '0.0000',
		private string $paymentFeeBase = '0.0000',
		private string $otherFeesBase = '0.0000',
	)
	{
	}

	public function getUnitCostBase(): ?string
	{
		return $this->unitCostBase;
	}

	public function getDeliveryCostBase(): string
	{
		return $this->deliveryCostBase;
	}

	public function getPaymentFeeBase(): string
	{
		return $this->paymentFeeBase;
	}

	public function getOtherFeesBase(): string
	{
		return $this->otherFeesBase;
	}

	public function getEffectiveUnitCostBase(): ?string
	{
		if ($this->unitCostBase === null) {
			return null;
		}

		return number_format(
			(float) $this->unitCostBase
			+ (float) $this->deliveryCostBase
			+ (float) $this->paymentFeeBase
			+ (float) $this->otherFeesBase,
			4,
			'.',
			''
		);
	}
}

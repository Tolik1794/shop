<?php

namespace App\Service\Order;

use App\Entity\OrderEntry;
use App\Enum\OrderEntryFulfillmentState;

/**
 * Computes the per-line fulfillment progress shown on the order detail.
 *
 * The arithmetic deliberately mirrors the existing remaining/returnable rules
 * ({@see \App\Service\Inventory\OrderShipmentUseCase::remainingQuantity()},
 * {@see \App\Service\Inventory\CustomerReturnUseCase::returnableQuantity()}) and the aggregate
 * status rules ({@see \App\Service\BusinessDocumentStatusSynchronizer::syncOrder()}), so a single
 * position never reports a state inconsistent with the order-level status.
 */
class OrderEntryProgressCalculator
{
	private const float EPSILON = 0.00005;

	public function calculate(OrderEntry $orderEntry): OrderEntryProgress
	{
		$ordered = $this->numberValue($orderEntry->getQuantity());
		$shipped = $this->numberValue($orderEntry->getShippedQuantity());
		$returned = $this->numberValue($orderEntry->getReturnedQuantity());
		$canceled = $this->numberValue($orderEntry->getCanceledQuantity());

		$expected = max(0.0, $ordered - $canceled);
		$remaining = max(0.0, $ordered - $canceled - $shipped);
		$returnable = max(0.0, $shipped - $returned);

		return new OrderEntryProgress(
			ordered: $this->format($ordered),
			shipped: $this->format($shipped),
			returned: $this->format($returned),
			canceled: $this->format($canceled),
			remaining: $this->format($remaining),
			returnable: $this->format($returnable),
			state: $this->resolveState($expected, $shipped, $returned),
		);
	}

	private function resolveState(float $expected, float $shipped, float $returned): OrderEntryFulfillmentState
	{
		// Nothing left to fulfill because the whole position was refused before any shipment.
		if ($expected <= self::EPSILON) {
			return OrderEntryFulfillmentState::REFUSED;
		}

		if ($returned > self::EPSILON && $this->isEnough($returned, $expected)) {
			return OrderEntryFulfillmentState::RETURNED;
		}

		if ($returned > self::EPSILON) {
			return OrderEntryFulfillmentState::PARTIALLY_RETURNED;
		}

		if ($this->isEnough($shipped, $expected)) {
			return OrderEntryFulfillmentState::SHIPPED;
		}

		if ($shipped > self::EPSILON) {
			return OrderEntryFulfillmentState::PARTIALLY_SHIPPED;
		}

		return OrderEntryFulfillmentState::PENDING;
	}

	private function isEnough(float $actual, float $expected): bool
	{
		return $actual + self::EPSILON >= $expected;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function format(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

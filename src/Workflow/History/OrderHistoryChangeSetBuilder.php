<?php

namespace App\Workflow\History;

use App\Entity\Order;
use App\Entity\OrderEntry;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;

class OrderHistoryChangeSetBuilder
{
	private const ORDER_DECIMAL_FIELDS = [
		'exchangeRateToBase',
		'totalAmount',
		'totalAmountBase',
		'discountAmount',
		'discountAmountBase',
	];

	private const ENTRY_DECIMAL_FIELDS = [
		'quantity',
		'unitPrice',
		'discountAmount',
		'discountPercent',
	];

	private const OPTIONAL_ZERO_DECIMAL_ENTRY_FIELDS = [
		'discountAmount',
		'discountPercent',
	];

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly HistoryValueComparator $historyValueComparator,
	)
	{
	}

	/**
	 * @return array<string, array{from: mixed, to: mixed}>
	 */
	public function buildOrderChanges(Order $order): array
	{
		$original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($order);
		$current = [
			'customer' => $order->getCustomer()?->getId(),
			'customerNameSnapshot' => $order->getCustomerNameSnapshot(),
			'customerPhoneSnapshot' => $order->getCustomerPhoneSnapshot(),
			'customerEmailSnapshot' => $order->getCustomerEmailSnapshot(),
			'currency' => $order->getCurrency()?->getCode(),
			'exchangeRateToBase' => $order->getExchangeRateToBase(),
			'deliveryAddress' => $order->getDeliveryAddress(),
			'totalAmount' => $order->getTotalAmount(),
			'totalAmountBase' => $order->getTotalAmountBase(),
			'discountAmount' => $order->getDiscountAmount(),
			'discountAmountBase' => $order->getDiscountAmountBase(),
		];
		$changes = [];

		foreach ($current as $field => $value) {
			$before = match ($field) {
				'customer' => ($original[$field] ?? null)?->getId(),
				'currency' => ($original[$field] ?? null)?->getCode(),
				default => $original[$field] ?? null,
			};

			if (!$this->historyValueComparator->hasSameValue($field, $before, $value, self::ORDER_DECIMAL_FIELDS)) {
				$changes[$field] = ['from' => $before, 'to' => $value];
			}
		}

		return $changes;
	}

	/**
	 * @return array<string, array{from: mixed, to: mixed}>
	 */
	public function buildEntryChanges(OrderEntry $orderEntry): array
	{
		$original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($orderEntry);

		if ($original === []) {
			return [];
		}

		$current = $this->buildEntryPayload($orderEntry);
		$changes = [];

		foreach ($current as $field => $value) {
			$before = match ($field) {
				'productId' => ($original['product'] ?? null)?->getId(),
				'warehouseId' => ($original['warehouse'] ?? null)?->getId(),
				'discountRuleId' => ($original['discountRule'] ?? null)?->getId(),
				'discountMode' => $this->enumValue($original['discountMode'] ?? null),
				default => $original[$field] ?? null,
			};
			if ($field === 'discountMode') {
				$before ??= 'none';
				$value ??= 'none';
			}

			if ($this->isOptionalZeroDecimalEntryChange($field, $before, $value)) {
				continue;
			}

			if (!$this->historyValueComparator->hasSameValue($field, $before, $value, self::ENTRY_DECIMAL_FIELDS)) {
				$changes[$field] = ['from' => $before, 'to' => $value];
			}
		}

		return $changes;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function buildEntryPayload(OrderEntry $orderEntry): array
	{
		return [
			'productId' => $orderEntry->getProduct()?->getId(),
			'productNameSnapshot' => $orderEntry->getProductNameSnapshot(),
			'productCodeSnapshot' => $orderEntry->getProductCodeSnapshot(),
			'quantity' => $orderEntry->getQuantity(),
			'unitPrice' => $orderEntry->getUnitPrice(),
			'discountAmount' => $orderEntry->getDiscountAmount(),
			'discountPercent' => $orderEntry->getDiscountPercent(),
			'discountMode' => $orderEntry->getDiscountMode()?->value,
			'discountRuleId' => $orderEntry->getDiscountRule()?->getId(),
			'discountRuleNameSnapshot' => $orderEntry->getDiscountRuleNameSnapshot(),
			'warehouseId' => $orderEntry->getWarehouse()?->getId(),
		];
	}

	private function enumValue(mixed $value): ?string
	{
		if ($value instanceof BackedEnum) {
			return (string) $value->value;
		}

		return is_string($value) ? $value : null;
	}

	private function isOptionalZeroDecimalEntryChange(string $field, mixed $before, mixed $after): bool
	{
		if (!in_array($field, self::OPTIONAL_ZERO_DECIMAL_ENTRY_FIELDS, true)) {
			return false;
		}

		$beforeDecimal = $this->historyValueComparator->normalizeDecimal($before);
		$afterDecimal = $this->historyValueComparator->normalizeDecimal($after);

		return ($before === null && $afterDecimal === '0') || ($after === null && $beforeDecimal === '0');
	}
}

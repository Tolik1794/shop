<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Repository\InventoryDocumentRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class OrderShipmentUseCase
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ConcurrencyGuard $concurrencyGuard,
		private readonly InventoryDocumentRepository $inventoryDocumentRepository,
	)
	{
	}

	public function createDraft(Order $order, ?string $number = null): InventoryDocument
	{
		return $this->entityManager->wrapInTransaction(function () use ($order, $number): InventoryDocument {
			$this->lockForDraft($order);

			return $this->createDraftLocked($order, $number);
		});
	}

	/**
	 * Creates a shipment draft for an explicit subset of positions/quantities so positions with
	 * different shipment times can be shipped separately. The selection maps order entry id to the
	 * requested quantity; each requested quantity must not exceed the position's remaining quantity.
	 *
	 * @param array<int|string, string|int|float> $selection [orderEntryId => requestedQuantity]
	 */
	public function createDraftForSelection(Order $order, array $selection, ?string $number = null): InventoryDocument
	{
		return $this->entityManager->wrapInTransaction(function () use ($order, $selection, $number): InventoryDocument {
			$this->lockForDraft($order);

			return $this->createDraftForSelectionLocked($order, $selection, $number);
		});
	}

	private function lockForDraft(Order $order): void
	{
		$this->concurrencyGuard->lock($order);

		if ($this->inventoryDocumentRepository->hasDraftForOrder($order, InventoryDocumentType::SALE_SHIPMENT)) {
			throw new RuntimeException('Order already has a draft shipment. Review or post it before creating another one.');
		}
	}

	private function createDraftLocked(Order $order, ?string $number = null): InventoryDocument
	{
		$document = $this->newDocument($order, $number);

		foreach ($order->getOrderEntries() as $orderEntry) {
			$remaining = $this->remainingQuantity($orderEntry);

			if ($remaining <= 0.00005) {
				continue;
			}

			$this->appendLine($document, $orderEntry, $remaining);
		}

		return $this->finalizeDocument($document, 'Order has no remaining quantity to ship.');
	}

	/**
	 * @param array<int|string, string|int|float> $selection
	 */
	private function createDraftForSelectionLocked(Order $order, array $selection, ?string $number = null): InventoryDocument
	{
		$document = $this->newDocument($order, $number);

		foreach ($order->getOrderEntries() as $orderEntry) {
			$entryId = $orderEntry->getId();

			if ($entryId === null || !array_key_exists($entryId, $selection)) {
				continue;
			}

			$requested = $this->numberValue($selection[$entryId]);

			if ($requested <= 0.00005) {
				continue;
			}

			$remaining = $this->remainingQuantity($orderEntry);

			if ($requested > $remaining + 0.00005) {
				throw new RuntimeException('Requested shipment quantity cannot exceed the remaining quantity of a position.');
			}

			$this->appendLine($document, $orderEntry, $requested);
		}

		return $this->finalizeDocument($document, 'No positions were selected to ship.');
	}

	private function newDocument(Order $order, ?string $number = null): InventoryDocument
	{
		if (!in_array($order->getStatus(), [
			OrderStatus::READY_TO_SHIP,
			OrderStatus::PARTIALLY_SHIPPED,
		], true)) {
			throw new RuntimeException('Order shipment can be created only for ready to ship or partially shipped orders.');
		}

		return (new InventoryDocument())
			->setStore($order->getStore())
			->setNumber($number ?? $this->number($order))
			->setType(InventoryDocumentType::SALE_SHIPMENT)
			->setOrder($order)
			->setCurrency($order->getCurrency())
			->setExchangeRateToBase($order->getExchangeRateToBase())
			->setDocumentDate(new DateTimeImmutable());
	}

	private function appendLine(InventoryDocument $document, OrderEntry $orderEntry, float $quantity): void
	{
		$product = $orderEntry->getProduct();
		$warehouse = $orderEntry->getWarehouse();

		if (!$product || !$warehouse) {
			throw new RuntimeException('Order shipment lines require product and warehouse.');
		}

		$unitPrice = $orderEntry->getUnitPrice() ?? '0.0000';
		$unitPriceBase = $orderEntry->getUnitPriceBase() ?? '0.0000';
		$lineTotal = $this->proportionalTotal($orderEntry->getTotalPrice(), $orderEntry->getQuantity(), $quantity);
		$lineTotalBase = $this->proportionalTotal($orderEntry->getTotalPriceBase(), $orderEntry->getQuantity(), $quantity);

		$document->addLine((new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection(InventoryDirection::OUT)
			->setQuantity($this->formatQuantity($quantity))
			->setUnitPrice($this->formatMoney($this->numberValue($unitPrice)))
			->setUnitPriceBase($this->formatMoney($this->numberValue($unitPriceBase)))
			->setTotalPrice($this->formatMoney($lineTotal))
			->setTotalPriceBase($this->formatMoney($lineTotalBase))
			->setOrderEntry($orderEntry));
	}

	private function finalizeDocument(InventoryDocument $document, string $emptyMessage): InventoryDocument
	{
		if ($document->getLines()->isEmpty()) {
			throw new RuntimeException($emptyMessage);
		}

		$totalAmount = 0.0;
		$totalAmountBase = 0.0;

		foreach ($document->getLines() as $line) {
			$totalAmount += $this->numberValue($line->getTotalPrice());
			$totalAmountBase += $this->numberValue($line->getTotalPriceBase());
		}

		$document
			->setTotalAmount($this->formatMoney($totalAmount))
			->setTotalAmountBase($this->formatMoney($totalAmountBase));

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	public function hasShippableLines(Order $order): bool
	{
		if (!in_array($order->getStatus(), [
			OrderStatus::READY_TO_SHIP,
			OrderStatus::PARTIALLY_SHIPPED,
		], true)) {
			return false;
		}

		foreach ($order->getOrderEntries() as $orderEntry) {
			if ($this->remainingQuantity($orderEntry) > 0.00005) {
				return true;
			}
		}

		return false;
	}

	private function remainingQuantity(OrderEntry $orderEntry): float
	{
		return max(
			0.0,
			$this->numberValue($orderEntry->getQuantity())
			- $this->numberValue($orderEntry->getCanceledQuantity())
			- $this->numberValue($orderEntry->getShippedQuantity()),
		);
	}

	private function proportionalTotal(?string $total, ?string $quantity, float $remaining): float
	{
		$quantityValue = $this->numberValue($quantity);

		if ($quantityValue <= 0.00005) {
			return 0.0;
		}

		return $this->numberValue($total) * ($remaining / $quantityValue);
	}

	private function number(Order $order): string
	{
		return sprintf(
			'SHP-%s-%s',
			$order->getId() ?? 'new',
			(new DateTimeImmutable())->format('YmdHisv'),
		);
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatQuantity(float $value): string
	{
		return number_format($value, 4, '.', '');
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

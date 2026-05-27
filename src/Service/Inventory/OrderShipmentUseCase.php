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
			$this->concurrencyGuard->lock($order);

			if ($this->inventoryDocumentRepository->hasDraftForOrder($order, InventoryDocumentType::SALE_SHIPMENT)) {
				throw new RuntimeException('Order already has a draft shipment. Review or post it before creating another one.');
			}

			return $this->createDraftLocked($order, $number);
		});
	}

	private function createDraftLocked(Order $order, ?string $number = null): InventoryDocument
	{
		if (!in_array($order->getStatus(), [
			OrderStatus::READY_TO_SHIP,
			OrderStatus::PARTIALLY_SHIPPED,
		], true)) {
			throw new RuntimeException('Order shipment can be created only for ready to ship or partially shipped orders.');
		}

		$document = (new InventoryDocument())
			->setStore($order->getStore())
			->setNumber($number ?? $this->number($order))
			->setType(InventoryDocumentType::SALE_SHIPMENT)
			->setOrder($order)
			->setCurrency($order->getCurrency())
			->setExchangeRateToBase($order->getExchangeRateToBase())
			->setDocumentDate(new DateTimeImmutable());

		$totalAmount = 0.0;
		$totalAmountBase = 0.0;

		foreach ($order->getOrderEntries() as $orderEntry) {
			$remaining = $this->remainingQuantity($orderEntry);

			if ($remaining <= 0.00005) {
				continue;
			}

			$product = $orderEntry->getProduct();
			$warehouse = $orderEntry->getWarehouse();

			if (!$product || !$warehouse) {
				throw new RuntimeException('Order shipment lines require product and warehouse.');
			}

			$quantity = $this->formatQuantity($remaining);
			$unitPrice = $orderEntry->getUnitPrice() ?? '0.0000';
			$unitPriceBase = $orderEntry->getUnitPriceBase() ?? '0.0000';
			$lineTotal = $this->proportionalTotal($orderEntry->getTotalPrice(), $orderEntry->getQuantity(), $remaining);
			$lineTotalBase = $this->proportionalTotal($orderEntry->getTotalPriceBase(), $orderEntry->getQuantity(), $remaining);

			$document->addLine((new InventoryDocumentLine())
				->setProduct($product)
				->setWarehouse($warehouse)
				->setDirection(InventoryDirection::OUT)
				->setQuantity($quantity)
				->setUnitPrice($this->formatMoney($this->numberValue($unitPrice)))
				->setUnitPriceBase($this->formatMoney($this->numberValue($unitPriceBase)))
				->setTotalPrice($this->formatMoney($lineTotal))
				->setTotalPriceBase($this->formatMoney($lineTotalBase))
				->setOrderEntry($orderEntry));

			$totalAmount += $lineTotal;
			$totalAmountBase += $lineTotalBase;
		}

		if ($document->getLines()->isEmpty()) {
			throw new RuntimeException('Order has no remaining quantity to ship.');
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

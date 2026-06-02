<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\InventoryReason;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use DateTimeImmutable;
use App\Repository\WarehouseStockRepository;
use App\Service\InventoryPostingService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class CustomerReturnUseCase
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly InventoryPostingService $inventoryPostingService,
		private readonly WarehouseStockRepository $warehouseStockRepository,
	)
	{
	}

	public function createDraft(
		Order $order,
		OrderEntry $orderEntry,
		string $quantity,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		$document = $this->newDocument($order, $reason, $number);
		$this->addLine($document, $order, $orderEntry, $quantity);

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	/**
	 * Creates one draft customer return for all currently returnable shipped quantities.
	 *
	 * @param iterable<OrderEntry> $orderEntries
	 */
	public function createDraftForReturnableEntries(
		Order $order,
		iterable $orderEntries,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		$document = $this->newDocument($order, $reason, $number);

		foreach ($orderEntries as $orderEntry) {
			if (!$orderEntry instanceof OrderEntry) {
				throw new RuntimeException('Customer return draft entries must be order entries.');
			}

			$returnableQuantity = $this->returnableQuantity($orderEntry);

			if ($returnableQuantity <= 0.00005) {
				continue;
			}

			$this->addLine($document, $order, $orderEntry, $this->formatQuantity($returnableQuantity));
		}

		if ($document->getLines()->isEmpty()) {
			throw new RuntimeException('Order has no returnable shipped quantity.');
		}

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	public function createAndPost(
		Order $order,
		OrderEntry $orderEntry,
		string $quantity,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		$document = $this->createDraft($order, $orderEntry, $quantity, $reason, $number);
		$this->inventoryPostingService->post($document);

		return $document;
	}

	private function number(string $prefix): string
	{
		return sprintf('%s-%s', $prefix, (new DateTimeImmutable())->format('YmdHisv'));
	}

	private function newDocument(Order $order, ?InventoryReason $reason = null, ?string $number = null): InventoryDocument
	{
		return (new InventoryDocument())
			->setStore($order->getStore())
			->setNumber($number ?? $this->number('CRN'))
			->setType(InventoryDocumentType::CUSTOMER_RETURN)
			->setOrder($order)
			->setReason($reason);
	}

	private function addLine(InventoryDocument $document, Order $order, OrderEntry $orderEntry, string $quantity): void
	{
		if ($orderEntry->getOrder() !== $order) {
			throw new RuntimeException('Customer return order entry must belong to the order.');
		}

		$product = $orderEntry->getProduct();
		$warehouse = $orderEntry->getWarehouse();

		if (!$product || !$warehouse) {
			throw new RuntimeException('Customer return order entry must have product and warehouse.');
		}

		$unitCost = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse)?->getAverageCost() ?? '0.0000';

		$document->addLine((new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection(InventoryDirection::IN)
			->setQuantity($quantity)
			->setUnitPriceBase($unitCost)
			->setOrderEntry($orderEntry));
	}

	private function returnableQuantity(OrderEntry $orderEntry): float
	{
		return max(
			0.0,
			$this->numberValue($orderEntry->getShippedQuantity())
			- $this->numberValue($orderEntry->getReturnedQuantity()),
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
}

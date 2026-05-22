<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\InventoryReason;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
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
		if ($orderEntry->getOrder() !== $order) {
			throw new RuntimeException('Customer return order entry must belong to the order.');
		}

		$product = $orderEntry->getProduct();
		$warehouse = $orderEntry->getWarehouse();

		if (!$product || !$warehouse) {
			throw new RuntimeException('Customer return order entry must have product and warehouse.');
		}

		$unitCost = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse)?->getAverageCost() ?? '0.0000';
		$document = (new InventoryDocument())
			->setStore($order->getStore())
			->setNumber($number ?? $this->number('CRN'))
			->setType(InventoryDocumentType::CUSTOMER_RETURN)
			->setOrder($order)
			->setReason($reason)
			->addLine((new InventoryDocumentLine())
				->setProduct($product)
				->setWarehouse($warehouse)
				->setDirection(InventoryDirection::IN)
				->setQuantity($quantity)
				->setUnitPriceBase($unitCost)
				->setOrderEntry($orderEntry));

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
		return sprintf('%s-%s', $prefix, (new \DateTimeImmutable())->format('YmdHis'));
	}
}

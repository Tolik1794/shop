<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\InventoryReason;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Repository\WarehouseStockRepository;
use App\Service\InventoryPostingService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class InventoryTransferUseCase
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly InventoryPostingService $inventoryPostingService,
		private readonly WarehouseStockRepository $warehouseStockRepository,
	)
	{
	}

	public function createDraft(
		Store $store,
		Product $product,
		Warehouse $sourceWarehouse,
		Warehouse $destinationWarehouse,
		string $quantity,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		$this->assertSameStore($store, $product, $sourceWarehouse, $destinationWarehouse);

		if ($sourceWarehouse === $destinationWarehouse) {
			throw new RuntimeException('Transfer source and destination warehouses must be different.');
		}

		$unitCost = $this->sourceAverageCost($product, $sourceWarehouse);
		$document = (new InventoryDocument())
			->setStore($store)
			->setNumber($number ?? $this->number('TRF'))
			->setType(InventoryDocumentType::TRANSFER)
			->setReason($reason);

		$document
			->addLine($this->line($product, $sourceWarehouse, InventoryDirection::OUT, $quantity, $unitCost))
			->addLine($this->line($product, $destinationWarehouse, InventoryDirection::IN, $quantity, $unitCost));

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	public function createAndPost(
		Store $store,
		Product $product,
		Warehouse $sourceWarehouse,
		Warehouse $destinationWarehouse,
		string $quantity,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		$document = $this->createDraft($store, $product, $sourceWarehouse, $destinationWarehouse, $quantity, $reason, $number);
		$this->inventoryPostingService->post($document);

		return $document;
	}

	private function assertSameStore(Store $store, Product $product, Warehouse ...$warehouses): void
	{
		if ($product->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Transfer product must belong to the document store.');
		}

		foreach ($warehouses as $warehouse) {
			if ($warehouse->getStore()?->getId() !== $store->getId()) {
				throw new RuntimeException('Transfer warehouses must belong to the document store.');
			}
		}
	}

	private function sourceAverageCost(Product $product, Warehouse $warehouse): string
	{
		return $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse)?->getAverageCost() ?? '0.0000';
	}

	private function line(Product $product, Warehouse $warehouse, InventoryDirection $direction, string $quantity, string $unitCost): InventoryDocumentLine
	{
		return (new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection($direction)
			->setQuantity($quantity)
			->setUnitPriceBase($unitCost);
	}

	private function number(string $prefix): string
	{
		return sprintf('%s-%s', $prefix, (new \DateTimeImmutable())->format('YmdHis'));
	}
}

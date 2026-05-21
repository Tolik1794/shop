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
use App\Service\InventoryPostingService;
use Doctrine\ORM\EntityManagerInterface;

class WriteOffAdjustmentUseCase
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly InventoryPostingService $inventoryPostingService,
	)
	{
	}

	public function createWriteOffDraft(
		Store $store,
		Product $product,
		Warehouse $warehouse,
		string $quantity,
		InventoryReason $reason,
		?string $number = null,
	): InventoryDocument
	{
		return $this->createDraft(
			$store,
			InventoryDocumentType::WRITE_OFF,
			$reason,
			[
				$this->line($product, $warehouse, InventoryDirection::OUT, $quantity, null),
			],
			$number ?? $this->number('WOF'),
		);
	}

	/**
	 * @param list<array{product: Product, warehouse: Warehouse, direction: InventoryDirection, quantity: string, unitCost?: string|null}> $lines
	 */
	public function createStockAdjustmentDraft(
		Store $store,
		InventoryReason $reason,
		array $lines,
		?string $number = null,
	): InventoryDocument
	{
		$documentLines = [];

		foreach ($lines as $line) {
			$documentLines[] = $this->line(
				$line['product'],
				$line['warehouse'],
				$line['direction'],
				$line['quantity'],
				$line['unitCost'] ?? null,
			);
		}

		return $this->createDraft($store, InventoryDocumentType::STOCK_ADJUSTMENT, $reason, $documentLines, $number ?? $this->number('ADJ'));
	}

	public function createAndPost(InventoryDocument $document): InventoryDocument
	{
		$this->inventoryPostingService->post($document);

		return $document;
	}

	/**
	 * @param list<InventoryDocumentLine> $lines
	 */
	private function createDraft(
		Store $store,
		InventoryDocumentType $type,
		InventoryReason $reason,
		array $lines,
		string $number,
	): InventoryDocument
	{
		$document = (new InventoryDocument())
			->setStore($store)
			->setNumber($number)
			->setType($type)
			->setReason($reason);

		foreach ($lines as $line) {
			$document->addLine($line);
		}

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	private function line(
		Product $product,
		Warehouse $warehouse,
		InventoryDirection $direction,
		string $quantity,
		?string $unitCost,
	): InventoryDocumentLine
	{
		$line = (new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection($direction)
			->setQuantity($quantity);

		if ($unitCost !== null) {
			$line->setUnitPriceBase($unitCost);
		}

		return $line;
	}

	private function number(string $prefix): string
	{
		return sprintf('%s-%s', $prefix, (new \DateTimeImmutable())->format('YmdHis'));
	}
}

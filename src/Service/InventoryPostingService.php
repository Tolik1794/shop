<?php

namespace App\Service;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\Purchase;
use App\Entity\StockMovement;
use App\Entity\Store;
use App\Entity\User\User;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Manager\UserManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class InventoryPostingService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly WarehouseStockService $warehouseStockService,
		private readonly UserManager $userManager,
		private readonly DocumentProgressRecalculator $documentProgressRecalculator,
	)
	{
	}

	public function post(InventoryDocument $document): void
	{
		$this->entityManager->wrapInTransaction(function () use ($document): void {
			$this->postDocument($document);
		});
	}

	public function cancel(InventoryDocument $document): ?InventoryDocument
	{
		return $this->entityManager->wrapInTransaction(function () use ($document): ?InventoryDocument {
			return $this->cancelDocument($document);
		});
	}

	public function createReversal(InventoryDocument $document): InventoryDocument
	{
		if ($document->getStatus() !== InventoryDocumentStatus::POSTED) {
			throw new RuntimeException('Only posted inventory documents can be reversed.');
		}

		$reversal = (new InventoryDocument())
			->setStore($document->getStore())
			->setNumber($this->reversalNumber($document))
			->setType(InventoryDocumentType::REVERSAL)
			->setDocumentDate(new DateTimeImmutable())
			->setCurrency($document->getCurrency())
			->setExchangeRateToBase($document->getExchangeRateToBase())
			->setTotalAmount($document->getTotalAmount())
			->setTotalAmountBase($document->getTotalAmountBase())
			->setComment(sprintf('Reversal of inventory document %s', $document->getNumber()))
			->setReversedDocument($document)
			->setOrder($document->getOrder())
			->setPurchase($document->getPurchase());

		foreach ($document->getLines() as $line) {
			$reversal->addLine((new InventoryDocumentLine())
				->setQuantity((string) $line->getQuantity())
				->setDirection($this->oppositeDirection($line->getDirection()))
				->setUnitPrice($line->getUnitPrice())
				->setUnitPriceBase($this->reversalUnitCostBase($line))
				->setTotalPrice($line->getTotalPrice())
				->setTotalPriceBase($line->getTotalPriceBase())
				->setProduct($line->getProduct())
				->setWarehouse($line->getWarehouse())
				->setOrderEntry($line->getOrderEntry())
				->setPurchaseEntry($line->getPurchaseEntry()));
		}

		return $reversal;
	}

	private function postDocument(InventoryDocument $document, bool $allowReversal = false): void
	{
		if ($document->getStatus() !== InventoryDocumentStatus::DRAFT) {
			throw new RuntimeException('Only draft inventory documents can be posted.');
		}

		if ($document->getType() === InventoryDocumentType::REVERSAL && !$allowReversal) {
			throw new RuntimeException('Reversal inventory documents can only be posted through cancel flow.');
		}

		$this->assertDocumentCanBePosted($document);
		$actor = $this->currentActor();
		$postedAt = new DateTimeImmutable();

		foreach ($document->getLines() as $line) {
			$this->postLine($line);
		}

		$document
			->setStatus(InventoryDocumentStatus::POSTED)
			->setPostedAt($postedAt)
			->setPostedBy($actor)
			->setUpdatedAt($postedAt)
			->setUpdatedBy($actor);

		$this->documentProgressRecalculator->recalculateForInventoryDocument($document);
		$this->entityManager->persist($document);
	}

	private function cancelDocument(InventoryDocument $document): ?InventoryDocument
	{
		if ($document->getStatus() === InventoryDocumentStatus::CANCELED) {
			return null;
		}

		$actor = $this->currentActor();
		$canceledAt = new DateTimeImmutable();
		$reversal = null;

		if ($document->getStatus() === InventoryDocumentStatus::POSTED) {
			$reversal = $this->createReversal($document);
			$this->entityManager->persist($reversal);
			$this->postDocument($reversal, true);
		}

		$document
			->setStatus(InventoryDocumentStatus::CANCELED)
			->setCanceledAt($canceledAt)
			->setCanceledBy($actor)
			->setUpdatedAt($canceledAt)
			->setUpdatedBy($actor);

		$this->documentProgressRecalculator->recalculateForInventoryDocument($document);
		$this->entityManager->persist($document);

		return $reversal;
	}

	private function postLine(InventoryDocumentLine $line): void
	{
		$document = $line->getInventoryDocument();
		$product = $line->getProduct();
		$warehouse = $line->getWarehouse();

		if (!$document || !$product || !$warehouse) {
			throw new RuntimeException('Inventory document line must have document, product and warehouse.');
		}

		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			return;
		}

		$warehouseStock = $this->warehouseStockService->findOrCreate($warehouse, $product);
		$quantity = $this->numberValue($line->getQuantity());
		$unitCost = $this->lineUnitCost($line, $warehouseStock);
		$oldQuantity = $this->numberValue($warehouseStock->getQuantityOnHand());
		$oldAverageCost = $this->numberValue($warehouseStock->getAverageCost());

		if ($line->getDirection() === InventoryDirection::IN) {
			$newQuantity = $oldQuantity + $quantity;
			$newAverageCost = $newQuantity > 0
				? (($oldQuantity * $oldAverageCost) + ($quantity * $unitCost)) / $newQuantity
				: $unitCost;
			$quantityChange = $quantity;

			$warehouseStock
				->setQuantityOnHand($this->formatQuantity($newQuantity))
				->setAverageCost($this->formatMoney($newAverageCost));
		} else {
			$newQuantity = $oldQuantity - $quantity;
			if ($newQuantity < -0.00005) {
				throw new StockOperationException('Quantity on hand cannot be negative.');
			}

			$quantityChange = -$quantity;
			$warehouseStock->setQuantityOnHand($this->formatQuantity(max(0, $newQuantity)));
		}

		$warehouseStock->setUpdatedAt(new DateTimeImmutable());
		$line->setWarehouseStock($warehouseStock);

		$movement = (new StockMovement())
			->setInventoryDocumentLine($line)
			->setWarehouseStock($warehouseStock)
			->setQuantityChange($this->formatQuantity($quantityChange))
			->setUnitCost($this->formatMoney($unitCost))
			->setBalanceAfter($warehouseStock->getQuantityOnHand());

		$line->addStockMovement($movement);
		$this->entityManager->persist($warehouseStock);
		$this->entityManager->persist($movement);
	}

	private function assertDocumentCanBePosted(InventoryDocument $document): void
	{
		$store = $document->getStore();
		if (!$store instanceof Store) {
			throw new RuntimeException('Inventory document store is required.');
		}

		if ($document->getLines()->isEmpty()) {
			throw new RuntimeException('Inventory document must have at least one line.');
		}

		$this->assertSingleBusinessDocument($document);

		foreach ($document->getLines() as $line) {
			$this->assertLineCanBePosted($line, $store);
		}
	}

	private function assertSingleBusinessDocument(InventoryDocument $document): void
	{
		$targetCount = (int) ($document->getOrder() instanceof Order)
			+ (int) ($document->getPurchase() instanceof Purchase);

		if ($targetCount > 1) {
			throw new RuntimeException('Inventory document can be linked to only one business document.');
		}
	}

	private function assertLineCanBePosted(InventoryDocumentLine $line, Store $store): void
	{
		$document = $line->getInventoryDocument();
		$product = $line->getProduct();
		$warehouse = $line->getWarehouse();
		$quantity = $this->numberValue($line->getQuantity());

		if ($quantity <= 0) {
			throw new RuntimeException('Inventory document line quantity must be greater than zero.');
		}

		if (!$product || $product->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Inventory document line product must belong to document store.');
		}

		if (!$warehouse || $warehouse->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Inventory document line warehouse must belong to document store.');
		}

		if ($document instanceof InventoryDocument) {
			$this->assertLineDirectionMatchesDocumentType($document, $line);
		}
	}

	private function lineUnitCost(InventoryDocumentLine $line, WarehouseStock $warehouseStock): float
	{
		$document = $line->getInventoryDocument();
		$value = $line->getUnitPriceBase() ?? $line->getUnitPrice() ?? $warehouseStock->getAverageCost() ?? '0';

		if (
			$line->getDirection() === InventoryDirection::OUT
			&& $document?->getType() !== InventoryDocumentType::REVERSAL
		) {
			$value = $warehouseStock->getAverageCost() ?? '0';
		}

		$unitCost = $this->numberValue($value);

		if ($unitCost < 0) {
			throw new RuntimeException('Inventory document line unit cost cannot be negative.');
		}

		return $unitCost;
	}

	private function assertLineDirectionMatchesDocumentType(InventoryDocument $document, InventoryDocumentLine $line): void
	{
		$expectedDirection = match ($document->getType()) {
			InventoryDocumentType::PURCHASE_RECEIPT,
			InventoryDocumentType::CUSTOMER_RETURN => InventoryDirection::IN,
			InventoryDocumentType::SALE_SHIPMENT,
			InventoryDocumentType::SUPPLIER_RETURN,
			InventoryDocumentType::WRITE_OFF => InventoryDirection::OUT,
			InventoryDocumentType::STOCK_ADJUSTMENT,
			InventoryDocumentType::TRANSFER,
			InventoryDocumentType::PRODUCTION,
			InventoryDocumentType::REVERSAL => null,
		};

		if ($expectedDirection !== null && $line->getDirection() !== $expectedDirection) {
			throw new RuntimeException(sprintf(
				'Inventory document type "%s" requires "%s" line direction.',
				$document->getType()->value,
				$expectedDirection->value,
			));
		}
	}

	private function oppositeDirection(InventoryDirection $direction): InventoryDirection
	{
		return $direction === InventoryDirection::IN ? InventoryDirection::OUT : InventoryDirection::IN;
	}

	private function reversalUnitCostBase(InventoryDocumentLine $line): ?string
	{
		$movement = $line->getStockMovements()->first();

		if ($movement instanceof StockMovement) {
			return $movement->getUnitCost();
		}

		return $line->getUnitPriceBase();
	}

	private function reversalNumber(InventoryDocument $document): string
	{
		return mb_substr(sprintf('%s-REV-%s', $document->getNumber(), (new DateTimeImmutable())->format('YmdHis')), 0, 255);
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
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

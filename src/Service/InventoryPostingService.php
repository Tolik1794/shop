<?php

namespace App\Service;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\ProductionOrder;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\StockMovement;
use App\Entity\Store;
use App\Entity\User\User;
use App\Entity\WarehouseStock;
use App\Enum\CostingMethodEnum;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\InventoryReasonType;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Manager\UserManager;
use App\Workflow\History\GenericStatusHistoryRecorder;
use App\Workflow\TransitionContext;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class InventoryPostingService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly WarehouseStockService $warehouseStockService,
		private readonly WarehouseStockBatchPostingService $warehouseStockBatchPostingService,
		private readonly UserManager $userManager,
		private readonly DocumentProgressRecalculator $documentProgressRecalculator,
		private readonly StockReservationService $stockReservationService,
		private readonly GenericStatusHistoryRecorder $statusHistoryRecorder,
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
			->setPurchase($document->getPurchase())
			->setProductionOrder($document->getProductionOrder());

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
		$this->prepareDocumentForPosting($document);
		$actor = $this->currentActor();
		$postedAt = new DateTimeImmutable();
		$fromStatus = $document->getStatus()->value;
		$context = $this->transitionContext($actor, $postedAt);
		$this->ensurePersistedIdentity($document);

		foreach ($this->linesForPosting($document) as $line) {
			$this->postLine($line);
		}

		$document
			->setStatus(InventoryDocumentStatus::POSTED)
			->setPostedAt($postedAt)
			->setPostedBy($actor)
			->setUpdatedAt($postedAt)
			->setUpdatedBy($actor);

		$this->entityManager->persist($document);
		$this->statusHistoryRecorder->recordChange($document, 'post', $fromStatus, InventoryDocumentStatus::POSTED->value, $context);
		$this->documentProgressRecalculator->recalculateForInventoryDocument($document, $context);
	}

	private function cancelDocument(InventoryDocument $document): ?InventoryDocument
	{
		if ($document->getStatus() === InventoryDocumentStatus::CANCELED) {
			return null;
		}

		$actor = $this->currentActor();
		$canceledAt = new DateTimeImmutable();
		$fromStatus = $document->getStatus()->value;
		$context = $this->transitionContext($actor, $canceledAt);
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

		$this->entityManager->persist($document);
		$this->ensurePersistedIdentity($document);
		$this->statusHistoryRecorder->recordChange($document, 'cancel', $fromStatus, InventoryDocumentStatus::CANCELED->value, $context);
		$this->documentProgressRecalculator->recalculateForInventoryDocument($document, $context);

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
		$unitCost = $this->postingUnitCost($line, $warehouseStock);
		$oldQuantity = $this->numberValue($warehouseStock->getQuantityOnHand());
		$oldAverageCost = $this->numberValue($warehouseStock->getAverageCost());

		if ($line->getDirection() === InventoryDirection::IN) {
			$incomingLayers = $this->incomingLayers($line, $unitCost);
			$unitCost = $this->weightedUnitCost($incomingLayers) ?? $unitCost;
			$newQuantity = $oldQuantity + $quantity;
			$newAverageCost = $newQuantity > 0
				? (($oldQuantity * $oldAverageCost) + ($quantity * $unitCost)) / $newQuantity
				: $unitCost;

			$warehouseStock
				->setQuantityOnHand($this->formatQuantity($newQuantity))
				->setAverageCost($this->formatMoney($newAverageCost));
		} else {
			$newQuantity = $oldQuantity - $quantity;
			if ($newQuantity < -0.00005) {
				throw new StockOperationException('Quantity on hand cannot be negative.');
			}
			$warehouseStock->setQuantityOnHand($this->formatQuantity(max(0, $newQuantity)));
		}

		$warehouseStock->setUpdatedAt(new DateTimeImmutable());
		$line->setWarehouseStock($warehouseStock);

		if ($document->getType() === InventoryDocumentType::REVERSAL && ($originalLine = $this->originalLineForReversal($line)) instanceof InventoryDocumentLine) {
			$movements = $this->warehouseStockBatchPostingService->createReversalMovements($line, $warehouseStock, $originalLine, $oldQuantity);
			if ($movements !== null) {
				$this->entityManager->persist($warehouseStock);
				$this->completeReservationsForShipment($line);

				return;
			}
		}

		if ($line->getDirection() === InventoryDirection::IN) {
			$this->warehouseStockBatchPostingService->createIncomingMovements($line, $warehouseStock, $incomingLayers ?? $this->incomingLayers($line, $unitCost), $oldQuantity);
		} else {
			$this->warehouseStockBatchPostingService->createOutgoingMovements($line, $warehouseStock, $quantity, $unitCost, $oldQuantity);
		}

		$this->entityManager->persist($warehouseStock);
		$this->completeReservationsForShipment($line);
	}

	private function completeReservationsForShipment(InventoryDocumentLine $line): void
	{
		$document = $line->getInventoryDocument();
		$orderEntry = $line->getOrderEntry();

		if (
			$document?->getType() !== InventoryDocumentType::SALE_SHIPMENT
			|| $line->getDirection() !== InventoryDirection::OUT
			|| !$orderEntry instanceof OrderEntry
		) {
			return;
		}

		$this->stockReservationService->completeForOrderEntry($orderEntry, (string) $line->getQuantity(), false);
	}

	/**
	 * @return list<InventoryDocumentLine>
	 */
	private function linesForPosting(InventoryDocument $document): array
	{
		$lines = $document->getLines()->toArray();

		if ($document->getType() !== InventoryDocumentType::TRANSFER) {
			return array_values($lines);
		}

		usort($lines, static fn (InventoryDocumentLine $left, InventoryDocumentLine $right): int => $left->getDirection() === $right->getDirection()
			? 0
			: ($left->getDirection() === InventoryDirection::OUT ? -1 : 1));

		return array_values($lines);
	}

	private function postingUnitCost(InventoryDocumentLine $line, WarehouseStock $warehouseStock): float
	{
		$document = $line->getInventoryDocument();

		if ($line->getDirection() === InventoryDirection::IN) {
			if (
				$document?->getType() === InventoryDocumentType::REVERSAL
				&& ($originalLine = $this->originalLineForReversal($line)) instanceof InventoryDocumentLine
				&& ($unitCost = $this->weightedMovementUnitCost($originalLine)) !== null
			) {
				return $unitCost;
			}

			if (
				$document?->getType() === InventoryDocumentType::TRANSFER
				&& $document->getStore()?->getCostingMethod() === CostingMethodEnum::FIFO
				&& ($sourceLine = $this->transferSourceLine($document)) instanceof InventoryDocumentLine
				&& ($unitCost = $this->weightedMovementUnitCost($sourceLine)) !== null
			) {
				return $unitCost;
			}
		}

		return $this->lineUnitCost($line, $warehouseStock);
	}

	/**
	 * @return list<array{quantity: float, unitCost: float, purchaseEntry?: PurchaseEntry|null, salePrice?: string|null}>
	 */
	private function incomingLayers(InventoryDocumentLine $line, float $unitCost): array
	{
		$document = $line->getInventoryDocument();

		if (
			$document?->getType() === InventoryDocumentType::TRANSFER
			&& $document->getStore()?->getCostingMethod() === CostingMethodEnum::FIFO
			&& ($sourceLine = $this->transferSourceLine($document)) instanceof InventoryDocumentLine
		) {
			$layers = $this->movementLayers($sourceLine);

			if ($layers !== []) {
				return $layers;
			}
		}

		$purchaseEntry = $line->getPurchaseEntry();

		return [[
			'quantity' => $this->numberValue($line->getQuantity()),
			'unitCost' => $unitCost,
			'purchaseEntry' => $purchaseEntry,
			'salePrice' => $purchaseEntry instanceof PurchaseEntry ? ($purchaseEntry->getSalePriceBase() ?? $purchaseEntry->getSalePrice()) : null,
		]];
	}

	/**
	 * @return list<array{quantity: float, unitCost: float, purchaseEntry?: PurchaseEntry|null, salePrice?: string|null}>
	 */
	private function movementLayers(InventoryDocumentLine $line): array
	{
		$layers = [];

		foreach ($line->getStockMovements() as $movement) {
			if (!$movement instanceof StockMovement) {
				continue;
			}

			$quantity = abs($this->numberValue($movement->getQuantityChange()));

			if ($quantity <= 0.00005) {
				continue;
			}

			$layers[] = [
				'quantity' => $quantity,
				'unitCost' => $this->numberValue($movement->getUnitCost()),
				'purchaseEntry' => null,
				'salePrice' => null,
			];
		}

		return $layers;
	}

	/**
	 * @param list<array{quantity: float, unitCost: float}> $layers
	 */
	private function weightedUnitCost(array $layers): ?float
	{
		$quantity = 0.0;
		$total = 0.0;

		foreach ($layers as $layer) {
			$quantity += $layer['quantity'];
			$total += $layer['quantity'] * $layer['unitCost'];
		}

		if ($quantity <= 0.00005) {
			return null;
		}

		return $total / $quantity;
	}

	private function weightedMovementUnitCost(InventoryDocumentLine $line): ?float
	{
		return $this->weightedUnitCost($this->movementLayers($line));
	}

	private function transferSourceLine(InventoryDocument $document): ?InventoryDocumentLine
	{
		foreach ($document->getLines() as $line) {
			if ($line->getDirection() === InventoryDirection::OUT) {
				return $line;
			}
		}

		return null;
	}

	private function originalLineForReversal(InventoryDocumentLine $line): ?InventoryDocumentLine
	{
		$document = $line->getInventoryDocument();
		$reversedDocument = $document?->getReversedDocument();

		if (!$document instanceof InventoryDocument || !$reversedDocument instanceof InventoryDocument) {
			return null;
		}

		$reversalLines = array_values($document->getLines()->toArray());
		$originalLines = array_values($reversedDocument->getLines()->toArray());

		foreach ($reversalLines as $index => $reversalLine) {
			if ($reversalLine === $line) {
				return $originalLines[$index] ?? null;
			}
		}

		return null;
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

		$this->assertReasonCanBeUsed($document, $store);
		$this->assertAdvancedOperationCanBePosted($document);
	}

	private function assertSingleBusinessDocument(InventoryDocument $document): void
	{
		$targetCount = (int) ($document->getOrder() instanceof Order)
			+ (int) ($document->getPurchase() instanceof Purchase)
			+ (int) ($document->getProductionOrder() instanceof ProductionOrder);

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

	private function assertReasonCanBeUsed(InventoryDocument $document, Store $store): void
	{
		$reason = $document->getReason();

		if (in_array($document->getType(), [
			InventoryDocumentType::WRITE_OFF,
			InventoryDocumentType::STOCK_ADJUSTMENT,
		], true) && $reason === null) {
			throw new RuntimeException(sprintf('Inventory document type "%s" requires an inventory reason.', $document->getType()->value));
		}

		if ($reason === null) {
			return;
		}

		if ($reason->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Inventory document reason must belong to document store.');
		}

		if ($reason->getStatus() !== ActiveStatusEnum::ACTIVE || $reason->getDeletedAt() !== null) {
			throw new RuntimeException('Inventory document reason must be active.');
		}

		$allowedTypes = match ($document->getType()) {
			InventoryDocumentType::WRITE_OFF => [
				InventoryReasonType::WRITE_OFF,
				InventoryReasonType::DAMAGE,
				InventoryReasonType::PRODUCTION_LOSS,
				InventoryReasonType::OTHER,
			],
			InventoryDocumentType::STOCK_ADJUSTMENT => [
				InventoryReasonType::STOCK_ADJUSTMENT,
				InventoryReasonType::INVENTORY_COUNT,
				InventoryReasonType::INITIAL_STOCK,
				InventoryReasonType::OTHER,
			],
			InventoryDocumentType::TRANSFER => [
				InventoryReasonType::TRANSFER,
				InventoryReasonType::OTHER,
			],
			InventoryDocumentType::CUSTOMER_RETURN,
			InventoryDocumentType::SUPPLIER_RETURN => [
				InventoryReasonType::RETURN,
				InventoryReasonType::OTHER,
			],
			default => null,
		};

		if ($allowedTypes !== null && !in_array($reason->getType(), $allowedTypes, true)) {
			throw new RuntimeException(sprintf('Inventory reason type "%s" is not valid for "%s" documents.', $reason->getType()->value, $document->getType()->value));
		}
	}

	private function assertAdvancedOperationCanBePosted(InventoryDocument $document): void
	{
		match ($document->getType()) {
			InventoryDocumentType::TRANSFER => $this->assertTransferCanBePosted($document),
			InventoryDocumentType::CUSTOMER_RETURN => $this->assertCustomerReturnCanBePosted($document),
			InventoryDocumentType::SUPPLIER_RETURN => $this->assertSupplierReturnCanBePosted($document),
			InventoryDocumentType::STOCK_ADJUSTMENT => $this->assertStockAdjustmentCanBePosted($document),
			default => null,
		};
	}

	private function assertTransferCanBePosted(InventoryDocument $document): void
	{
		if ($document->getLines()->count() !== 2) {
			throw new RuntimeException('Transfer inventory documents require exactly one OUT line and one IN line.');
		}

		$outLine = null;
		$inLine = null;

		foreach ($document->getLines() as $line) {
			if ($line->getDirection() === InventoryDirection::OUT) {
				$outLine = $line;
			}

			if ($line->getDirection() === InventoryDirection::IN) {
				$inLine = $line;
			}
		}

		if (!$outLine instanceof InventoryDocumentLine || !$inLine instanceof InventoryDocumentLine) {
			throw new RuntimeException('Transfer inventory documents require exactly one OUT line and one IN line.');
		}

		if ($outLine->getProduct() !== $inLine->getProduct()) {
			throw new RuntimeException('Transfer inventory document lines must use the same product.');
		}

		if ($outLine->getWarehouse() === $inLine->getWarehouse()) {
			throw new RuntimeException('Transfer inventory document source and destination warehouses must be different.');
		}

		if (!$this->hasSameQuantity($outLine->getQuantity(), $inLine->getQuantity())) {
			throw new RuntimeException('Transfer inventory document OUT and IN quantities must match.');
		}
	}

	private function assertCustomerReturnCanBePosted(InventoryDocument $document): void
	{
		if (!$document->getOrder() instanceof Order) {
			throw new RuntimeException('Customer return inventory documents must be linked to an order.');
		}

		foreach ($document->getLines() as $line) {
			$orderEntry = $line->getOrderEntry();

			if (!$orderEntry instanceof OrderEntry || $orderEntry->getOrder() !== $document->getOrder()) {
				throw new RuntimeException('Customer return lines must be linked to an order entry from the same order.');
			}

			if ($line->getProduct() !== $orderEntry->getProduct()) {
				throw new RuntimeException('Customer return line product must match the order entry product.');
			}

			$remaining = $this->numberValue($orderEntry->getShippedQuantity()) - $this->numberValue($orderEntry->getReturnedQuantity());

			if ($this->numberValue($line->getQuantity()) > $remaining + 0.00005) {
				throw new RuntimeException('Customer return quantity cannot exceed shipped quantity that has not already been returned.');
			}
		}
	}

	private function assertSupplierReturnCanBePosted(InventoryDocument $document): void
	{
		if (!$document->getPurchase() instanceof Purchase) {
			throw new RuntimeException('Supplier return inventory documents must be linked to a purchase.');
		}

		foreach ($document->getLines() as $line) {
			$purchaseEntry = $line->getPurchaseEntry();

			if (!$purchaseEntry instanceof PurchaseEntry || $purchaseEntry->getPurchase() !== $document->getPurchase()) {
				throw new RuntimeException('Supplier return lines must be linked to a purchase entry from the same purchase.');
			}

			if ($line->getProduct() !== $purchaseEntry->getProduct()) {
				throw new RuntimeException('Supplier return line product must match the purchase entry product.');
			}

			$remaining = $this->numberValue($purchaseEntry->getReceivedQuantity()) - $this->numberValue($purchaseEntry->getReturnedQuantity());

			if ($this->numberValue($line->getQuantity()) > $remaining + 0.00005) {
				throw new RuntimeException('Supplier return quantity cannot exceed received quantity that has not already been returned.');
			}
		}
	}

	private function assertStockAdjustmentCanBePosted(InventoryDocument $document): void
	{
		foreach ($document->getLines() as $line) {
			if (
				$line->getDirection() === InventoryDirection::IN
				&& $line->getUnitPriceBase() === null
				&& $line->getUnitPrice() === null
			) {
				throw new RuntimeException('Stock adjustment IN lines require an explicit unit cost.');
			}
		}
	}

	private function prepareDocumentForPosting(InventoryDocument $document): void
	{
		if ($document->getType() !== InventoryDocumentType::TRANSFER) {
			return;
		}

		$outLine = null;
		$inLine = null;

		foreach ($document->getLines() as $line) {
			if ($line->getDirection() === InventoryDirection::OUT) {
				$outLine = $line;
			}

			if ($line->getDirection() === InventoryDirection::IN) {
				$inLine = $line;
			}
		}

		if (
			!$outLine instanceof InventoryDocumentLine
			|| !$inLine instanceof InventoryDocumentLine
			|| !$outLine->getWarehouse()
			|| !$outLine->getProduct()
		) {
			return;
		}

		$sourceStock = $this->warehouseStockService->findOrCreate($outLine->getWarehouse(), $outLine->getProduct());
		$costSnapshot = $sourceStock->getAverageCost() ?? '0.0000';

		$outLine->setUnitPriceBase($costSnapshot);
		$inLine->setUnitPriceBase($costSnapshot);
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

	private function transitionContext(?User $actor, DateTimeImmutable $occurredAt): TransitionContext
	{
		return $actor instanceof User
			? TransitionContext::manual($actor, occurredAt: $occurredAt)
			: TransitionContext::system(occurredAt: $occurredAt);
	}

	private function ensurePersistedIdentity(InventoryDocument $document): void
	{
		if ($document->getId() !== null) {
			return;
		}

		$this->entityManager->persist($document);
		$this->entityManager->flush();
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function hasSameQuantity(mixed $left, mixed $right): bool
	{
		return abs($this->numberValue($left) - $this->numberValue($right)) < 0.00005;
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

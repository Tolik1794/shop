<?php

namespace App\Service;

use App\Entity\InventoryDocumentLine;
use App\Entity\PurchaseEntry;
use App\Entity\StockMovement;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Enum\CostingMethodEnum;
use App\Exception\StockOperationException;
use App\Repository\WarehouseStockBatchRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class WarehouseStockBatchPostingService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly WarehouseStockBatchRepository $warehouseStockBatchRepository,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	/**
	 * @param list<array{quantity: float, unitCost: float, purchaseEntry?: PurchaseEntry|null, salePrice?: string|null}> $layers
	 * @return list<StockMovement>
	 */
	public function createIncomingMovements(
		InventoryDocumentLine $line,
		WarehouseStock $warehouseStock,
		array $layers,
		float $initialBalance,
	): array {
		$movements = [];
		$balance = $initialBalance;

		foreach ($layers as $layer) {
			$quantity = $layer['quantity'];

			if ($quantity <= 0.00005) {
				continue;
			}

			$unitCost = $layer['unitCost'];
			$balance += $quantity;

			$movement = $this->movement($line, $warehouseStock, $quantity, $unitCost, $balance);
			$batch = (new WarehouseStockBatch())
				->setWarehouseStock($warehouseStock)
				->setInitialQuantity($this->formatQuantity($quantity))
				->setRemainingQuantity($this->formatQuantity($quantity))
				->setUnitCost($this->formatMoney($unitCost))
				->setSalePrice($layer['salePrice'] ?? null)
				->setReceivedAt($line->getInventoryDocument()?->getDocumentDate() ?? $movement->getCreatedAt())
				->setPurchaseEntry($layer['purchaseEntry'] ?? $line->getPurchaseEntry())
				->setCreatedByMovement($movement);

			$movement->setWarehouseStockBatch($batch);
			$batch->addStockMovement($movement);
			$warehouseStock->addWarehouseStockBatch($batch);
			$line->addStockMovement($movement);

			$this->entityManager->persist($batch);
			$this->entityManager->persist($movement);
			$movements[] = $movement;
		}

		return $movements;
	}

	/**
	 * @return list<StockMovement>
	 */
	public function createOutgoingMovements(
		InventoryDocumentLine $line,
		WarehouseStock $warehouseStock,
		float $quantity,
		float $fallbackUnitCost,
		float $initialBalance,
	): array {
		$this->ensureOpeningBatchForLegacyQuantity($warehouseStock, $initialBalance);

		$remaining = $quantity;
		$balance = $initialBalance;
		$movements = [];
		$useFifoCost = $line->getInventoryDocument()?->getStore()?->getCostingMethod() === CostingMethodEnum::FIFO;
		$selectedBatch = $line->getOrderEntry()?->getWarehouseStockBatch();

		if ($selectedBatch instanceof WarehouseStockBatch) {
			$this->concurrencyGuard->lock($selectedBatch);

			return [$this->consumeBatch(
				line: $line,
				warehouseStock: $warehouseStock,
				batch: $selectedBatch,
				quantity: $quantity,
				fallbackUnitCost: $fallbackUnitCost,
				balance: $balance,
				useFifoCost: $useFifoCost,
			)];
		}

		$batches = $this->openBatches($warehouseStock);
		$this->concurrencyGuard->lockAll($batches);

		foreach ($batches as $batch) {
			if ($remaining <= 0.00005) {
				break;
			}

			$batchRemaining = $this->numberValue($batch->getRemainingQuantity());
			if ($batchRemaining <= 0.00005) {
				continue;
			}

			$consumed = min($remaining, $batchRemaining);
			$remaining -= $consumed;
			$balance -= $consumed;
			$batch->setRemainingQuantity($this->formatQuantity($batchRemaining - $consumed));

			$movement = $this->movement(
				$line,
				$warehouseStock,
				-$consumed,
				$useFifoCost ? $this->numberValue($batch->getUnitCost()) : $fallbackUnitCost,
				$balance,
			);
			$movement->setWarehouseStockBatch($batch);
			$batch->addStockMovement($movement);
			$line->addStockMovement($movement);

			$this->entityManager->persist($batch);
			$this->entityManager->persist($movement);
			$movements[] = $movement;
		}

		if ($remaining > 0.00005) {
			throw new StockOperationException('Not enough open stock batches to consume.');
		}

		return $movements;
	}

	private function consumeBatch(
		InventoryDocumentLine $line,
		WarehouseStock $warehouseStock,
		WarehouseStockBatch $batch,
		float $quantity,
		float $fallbackUnitCost,
		float $balance,
		bool $useFifoCost,
	): StockMovement {
		if ($batch->getWarehouseStock() !== $warehouseStock) {
			throw new StockOperationException('Selected stock batch does not match warehouse stock.');
		}

		$batchRemaining = $this->numberValue($batch->getRemainingQuantity());
		if ($quantity > $batchRemaining + 0.00005) {
			throw new StockOperationException('Selected stock batch does not have enough remaining quantity.');
		}

		$balance -= $quantity;
		$batch->setRemainingQuantity($this->formatQuantity($batchRemaining - $quantity));

		$movement = $this->movement(
			$line,
			$warehouseStock,
			-$quantity,
			$useFifoCost ? $this->numberValue($batch->getUnitCost()) : $fallbackUnitCost,
			$balance,
		);
		$movement->setWarehouseStockBatch($batch);
		$batch->addStockMovement($movement);
		$line->addStockMovement($movement);

		$this->entityManager->persist($batch);
		$this->entityManager->persist($movement);

		return $movement;
	}

	/**
	 * @return list<StockMovement>|null
	 */
	public function createReversalMovements(
		InventoryDocumentLine $line,
		WarehouseStock $warehouseStock,
		InventoryDocumentLine $originalLine,
		float $initialBalance,
	): ?array {
		$originalMovements = $originalLine->getStockMovements()->toArray();

		if ($originalMovements === []) {
			return null;
		}

		foreach ($originalMovements as $originalMovement) {
			if (!$originalMovement instanceof StockMovement || !$originalMovement->getWarehouseStockBatch() instanceof WarehouseStockBatch) {
				return null;
			}
		}

		$balance = $initialBalance;
		$movements = [];

		foreach ($originalMovements as $originalMovement) {
			$batch = $originalMovement->getWarehouseStockBatch();
			$this->concurrencyGuard->lock($batch);
			$quantityChange = -$this->numberValue($originalMovement->getQuantityChange());

			if (abs($quantityChange) <= 0.00005) {
				continue;
			}

			$batchRemaining = $this->numberValue($batch->getRemainingQuantity());
			$newBatchRemaining = $batchRemaining + $quantityChange;

			if ($newBatchRemaining < -0.00005) {
				throw new StockOperationException('Batch remaining quantity cannot be negative.');
			}

			$batch->setRemainingQuantity($this->formatQuantity(max(0, $newBatchRemaining)));
			$balance += $quantityChange;

			$movement = $this->movement(
				$line,
				$warehouseStock,
				$quantityChange,
				$this->numberValue($originalMovement->getUnitCost()),
				$balance,
			);
			$movement->setWarehouseStockBatch($batch);
			$batch->addStockMovement($movement);
			$line->addStockMovement($movement);

			$this->entityManager->persist($batch);
			$this->entityManager->persist($movement);
			$movements[] = $movement;
		}

		return $movements;
	}

	private function ensureOpeningBatchForLegacyQuantity(WarehouseStock $warehouseStock, float $quantityOnHand): void
	{
		$openRemaining = max(
			$this->numberValue($this->warehouseStockBatchRepository->sumOpenRemainingByWarehouseStock($warehouseStock)),
			$this->collectionOpenRemaining($warehouseStock),
		);
		$missingQuantity = $quantityOnHand - $openRemaining;

		if ($missingQuantity <= 0.00005) {
			return;
		}

		$batch = (new WarehouseStockBatch())
			->setWarehouseStock($warehouseStock)
			->setInitialQuantity($this->formatQuantity($missingQuantity))
			->setRemainingQuantity($this->formatQuantity($missingQuantity))
			->setUnitCost($warehouseStock->getAverageCost() ?? '0.0000')
			->setSalePrice(null)
			->setReceivedAt($warehouseStock->getCreatedAt())
			->setCreatedAt($warehouseStock->getCreatedAt());

		$warehouseStock->addWarehouseStockBatch($batch);
		$this->entityManager->persist($batch);
	}

	private function collectionOpenRemaining(WarehouseStock $warehouseStock): float
	{
		$total = 0.0;

		foreach ($warehouseStock->getWarehouseStockBatches() as $batch) {
			$total += max(0.0, $this->numberValue($batch->getRemainingQuantity()));
		}

		return $total;
	}

	/**
	 * @return list<WarehouseStockBatch>
	 */
	private function openBatches(WarehouseStock $warehouseStock): array
	{
		$batches = array_merge(
			$this->warehouseStockBatchRepository->findOpenByWarehouseStock($warehouseStock),
			$warehouseStock->getWarehouseStockBatches()
				->filter(static fn (WarehouseStockBatch $batch): bool => (float) $batch->getRemainingQuantity() > 0)
				->toArray(),
		);

		$unique = [];
		foreach ($batches as $batch) {
			$key = $batch->getId() !== null ? 'id-' . $batch->getId() : 'object-' . spl_object_id($batch);
			$unique[$key] = $batch;
		}

		$batches = array_values($unique);
		usort($batches, static function (WarehouseStockBatch $left, WarehouseStockBatch $right): int {
			$dateCompare = $left->getReceivedAt() <=> $right->getReceivedAt();

			if ($dateCompare !== 0) {
				return $dateCompare;
			}

			return ($left->getId() ?? 0) <=> ($right->getId() ?? 0);
		});

		return $batches;
	}

	private function movement(
		InventoryDocumentLine $line,
		WarehouseStock $warehouseStock,
		float $quantityChange,
		float $unitCost,
		float $balanceAfter,
	): StockMovement {
		return (new StockMovement())
			->setInventoryDocumentLine($line)
			->setWarehouseStock($warehouseStock)
			->setQuantityChange($this->formatQuantity($quantityChange))
			->setUnitCost($this->formatMoney($unitCost))
			->setBalanceAfter($this->formatQuantity(max(0, $balanceAfter)));
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		if (!is_finite($number)) {
			throw new RuntimeException('Invalid numeric stock batch value.');
		}

		return $number;
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

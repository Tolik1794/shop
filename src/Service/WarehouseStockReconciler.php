<?php

namespace App\Service;

use App\Entity\StockMovement;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Repository\StockMovementRepository;
use App\Repository\WarehouseStockBatchRepository;
use App\Repository\WarehouseStockRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class WarehouseStockReconciler
{
	private const EPSILON = 0.00005;

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly WarehouseStockBatchRepository $warehouseStockBatchRepository,
		private readonly StockMovementRepository $stockMovementRepository,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	/**
	 * @return array{apply: bool, stocks: list<array<string, int|string|bool>>}
	 */
	public function reconcile(?int $storeId = null, ?int $stockId = null, bool $apply = false): array
	{
		if (!$apply) {
			return [
				'apply' => false,
				'stocks' => $this->buildReports($this->warehouseStockRepository->findForMovementReconciliation($storeId, $stockId), false),
			];
		}

		return $this->entityManager->wrapInTransaction(function () use ($storeId, $stockId): array {
			$stocks = $this->warehouseStockRepository->findForMovementReconciliation($storeId, $stockId);
			$lockable = [];

			foreach ($stocks as $stock) {
				$lockable[] = $stock;
				array_push($lockable, ...$this->warehouseStockBatchRepository->findByWarehouseStock($stock));
				array_push($lockable, ...$this->stockMovementRepository->findByWarehouseStockInPostingOrder($stock));
			}

			$this->concurrencyGuard->lockAll($lockable);
			$reports = $this->buildReports($stocks, true);
			$this->entityManager->flush();

			return ['apply' => true, 'stocks' => $reports];
		});
	}

	/**
	 * @param WarehouseStock[] $stocks
	 *
	 * @return list<array<string, int|string|bool>>
	 */
	private function buildReports(array $stocks, bool $apply): array
	{
		$reports = [];

		foreach ($stocks as $stock) {
			$report = $this->replay($stock, $apply);

			if (
				$report['safe'] === false
				|| $report['stock_changed'] === true
				|| $report['batch_changes'] > 0
				|| $report['movement_changes'] > 0
				|| $report['opening_batch_created'] === true
			) {
				$reports[] = $report;
			}
		}

		return $reports;
	}

	/**
	 * @return array<string, int|string|bool>
	 */
	private function replay(WarehouseStock $stock, bool $apply): array
	{
		$storedQuantity = $this->normalize($stock->getQuantityOnHand());
		$movements = $this->stockMovementRepository->findByWarehouseStockInPostingOrder($stock);
		$batches = $this->warehouseStockBatchRepository->findByWarehouseStock($stock);
		$firstMovement = $movements[0] ?? null;
		$unsafeReason = '';

		if (!$firstMovement instanceof StockMovement || $firstMovement->getBalanceAfter() === null) {
			return $this->unsafeReport($stock, 'The first movement has no balance_after value.');
		}

		$openingQuantity = $this->number($firstMovement->getBalanceAfter()) - $this->number($firstMovement->getQuantityChange());
		if ($openingQuantity < -self::EPSILON) {
			return $this->unsafeReport($stock, 'The inferred opening quantity is negative.');
		}

		$openingQuantity = max(0.0, $openingQuantity);
		$batchBalances = [];
		$activeBatches = [];
		$openingBatches = array_values(array_filter(
			$batches,
			static fn (WarehouseStockBatch $batch): bool => $batch->getCreatedByMovement() === null,
		));
		$this->sortBatches($openingBatches);

		$unallocatedOpening = $openingQuantity;
		foreach ($batches as $batch) {
			$batchBalances[$this->batchKey($batch)] = 0.0;
		}

		foreach ($openingBatches as $batch) {
			$key = $this->batchKey($batch);
			$allocated = min($unallocatedOpening, max(0.0, $this->number($batch->getInitialQuantity())));
			$batchBalances[$key] = $allocated;
			$activeBatches[$key] = $batch;
			$unallocatedOpening -= $allocated;
		}

		$openingBatchCreated = $unallocatedOpening > self::EPSILON;
		$newOpeningBatch = null;
		if ($openingBatchCreated) {
			$newOpeningBatch = (new WarehouseStockBatch())
				->setWarehouseStock($stock)
				->setInitialQuantity($this->normalize($unallocatedOpening))
				->setRemainingQuantity($this->normalize($unallocatedOpening))
				->setUnitCost($this->normalize($stock->getAverageCost()))
				->setReceivedAt($stock->getCreatedAt())
				->setCreatedAt(new DateTimeImmutable());
			$key = $this->batchKey($newOpeningBatch);
			$batchBalances[$key] = $unallocatedOpening;
			$activeBatches[$key] = $newOpeningBatch;
			$batches[] = $newOpeningBatch;
		}

		$balance = $openingQuantity;
		$movementBalances = [];

		foreach ($movements as $movement) {
			$change = $this->number($movement->getQuantityChange());
			$linkedBatch = $movement->getWarehouseStockBatch();

			if ($change > self::EPSILON) {
				if (!$linkedBatch instanceof WarehouseStockBatch) {
					// Legacy movements predate mandatory batch links. Keep their history untouched and
					// add the quantity to the oldest active layer, matching the legacy aggregate behavior.
					$fifo = array_values($activeBatches);
					$this->sortBatches($fifo);
					$linkedBatch = $fifo[0] ?? null;
					if (!$linkedBatch instanceof WarehouseStockBatch) {
						$unsafeReason = sprintf('Incoming movement %d has no batch or active legacy layer.', $movement->getId() ?? 0);
						break;
					}
				}

				$key = $this->batchKey($linkedBatch);
				$batchBalances[$key] = ($batchBalances[$key] ?? 0.0) + $change;
				$activeBatches[$key] = $linkedBatch;
			} elseif ($change < -self::EPSILON) {
				$remaining = abs($change);

				if ($linkedBatch instanceof WarehouseStockBatch) {
					$remaining -= $this->consume($linkedBatch, $remaining, $batchBalances);
				}

				if ($remaining > self::EPSILON) {
					$fifo = array_values($activeBatches);
					$this->sortBatches($fifo);

					foreach ($fifo as $batch) {
						if ($remaining <= self::EPSILON) {
							break;
						}

						$remaining -= $this->consume($batch, $remaining, $batchBalances);
					}
				}

				if ($remaining > self::EPSILON) {
					$unsafeReason = sprintf('Outgoing movement %d cannot be allocated to available batches.', $movement->getId() ?? 0);
					break;
				}
			}

			$balance += $change;
			if ($balance < -self::EPSILON) {
				$unsafeReason = sprintf('Movement %d makes the stock balance negative.', $movement->getId() ?? 0);
				break;
			}

			$movementBalances[$movement->getId() ?? spl_object_id($movement)] = max(0.0, $balance);
		}

		if ($unsafeReason !== '') {
			return $this->unsafeReport($stock, $unsafeReason);
		}

		$stockChanged = !$this->same($stock->getQuantityOnHand(), $balance);
		$movementChanges = 0;
		foreach ($movements as $movement) {
			$expected = $movementBalances[$movement->getId() ?? spl_object_id($movement)];
			if (!$this->same($movement->getBalanceAfter(), $expected)) {
				$movementChanges++;
				if ($apply) {
					$movement->setBalanceAfter($this->normalize($expected));
				}
			}
		}

		$batchChanges = 0;
		foreach ($batches as $batch) {
			$expected = $batchBalances[$this->batchKey($batch)] ?? 0.0;
			if (!$this->same($batch->getRemainingQuantity(), $expected)) {
				$batchChanges++;
				if ($apply) {
					$batch->setRemainingQuantity($this->normalize($expected));
				}
			}
		}

		if ($apply) {
			if ($stockChanged) {
				$stock->setQuantityOnHand($this->normalize($balance))->setUpdatedAt(new DateTimeImmutable());
			}
			if ($newOpeningBatch instanceof WarehouseStockBatch) {
				$stock->addWarehouseStockBatch($newOpeningBatch);
				$this->entityManager->persist($newOpeningBatch);
			}
		}

		return [
			'stock_id' => $stock->getId() ?? 0,
			'product' => $stock->getProduct()?->getName() ?? '',
			'stored' => $storedQuantity,
			'expected' => $this->normalize($balance),
			'stock_changed' => $stockChanged,
			'batch_changes' => $batchChanges,
			'movement_changes' => $movementChanges,
			'opening_batch_created' => $openingBatchCreated,
			'safe' => true,
			'reason' => '',
		];
	}

	/**
	 * @param array<string, float> $balances
	 */
	private function consume(WarehouseStockBatch $batch, float $requested, array &$balances): float
	{
		$key = $this->batchKey($batch);
		$available = max(0.0, $balances[$key] ?? 0.0);
		$consumed = min($requested, $available);
		$balances[$key] = $available - $consumed;

		return $consumed;
	}

	/**
	 * @param WarehouseStockBatch[] $batches
	 */
	private function sortBatches(array &$batches): void
	{
		usort($batches, static function (WarehouseStockBatch $left, WarehouseStockBatch $right): int {
			$date = $left->getReceivedAt() <=> $right->getReceivedAt();

			return $date !== 0 ? $date : (($left->getId() ?? PHP_INT_MAX) <=> ($right->getId() ?? PHP_INT_MAX));
		});
	}

	/**
	 * @return array<string, int|string|bool>
	 */
	private function unsafeReport(WarehouseStock $stock, string $reason): array
	{
		return [
			'stock_id' => $stock->getId() ?? 0,
			'product' => $stock->getProduct()?->getName() ?? '',
			'stored' => $this->normalize($stock->getQuantityOnHand()),
			'expected' => '',
			'stock_changed' => false,
			'batch_changes' => 0,
			'movement_changes' => 0,
			'opening_batch_created' => false,
			'safe' => false,
			'reason' => $reason,
		];
	}

	private function batchKey(WarehouseStockBatch $batch): string
	{
		return $batch->getId() !== null ? 'id-' . $batch->getId() : 'object-' . spl_object_id($batch);
	}

	private function same(string|float|int|null $left, string|float|int|null $right): bool
	{
		return abs($this->number($left) - $this->number($right)) <= self::EPSILON;
	}

	private function number(string|float|int|null $value): float
	{
		return (float) ($value ?? 0);
	}

	private function normalize(string|float|int|null $value): string
	{
		return number_format($this->number($value), 4, '.', '');
	}
}

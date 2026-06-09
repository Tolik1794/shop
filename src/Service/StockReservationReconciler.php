<?php

namespace App\Service;

use App\Entity\OrderEntry;
use App\Entity\StockReservationStatus;
use App\Entity\WarehouseStock;
use App\Repository\StockReservationRepository;
use App\Repository\WarehouseStockRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use Doctrine\ORM\EntityManagerInterface;

class StockReservationReconciler
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly StockReservationRepository $stockReservationRepository,
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly StockReservationService $stockReservationService,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	/**
	 * @return array{apply: bool, entries: list<array<string, int|string>>, stocks: list<array<string, int|string>>}
	 */
	public function reconcile(?int $storeId = null, ?int $orderId = null, bool $apply = false): array
	{
		if (!$apply) {
			$entries = $this->stockReservationRepository->findOrderEntriesWithActiveReservations($storeId, $orderId);
			$stocks = $this->warehouseStockRepository->findForReservationReconciliation($storeId, $orderId);

			return $this->buildReport($entries, $stocks, false);
		}

		return $this->entityManager->wrapInTransaction(function () use ($storeId, $orderId): array {
			$entries = $this->stockReservationRepository->findOrderEntriesWithActiveReservations($storeId, $orderId);
			$stocks = $this->warehouseStockRepository->findForReservationReconciliation($storeId, $orderId);
			$this->concurrencyGuard->lockAll([
				...array_map(static fn (OrderEntry $entry): mixed => $entry->getOrder(), $entries),
				...$entries,
				...$stocks,
			]);

			$report = $this->buildReport($entries, $stocks, true);

			foreach ($report['entries'] as $entryReport) {
				$entry = $entryReport['entity'];
				if (!$entry instanceof OrderEntry) {
					continue;
				}

				if ((float) $entryReport['complete'] > 0) {
					$this->stockReservationService->completeForOrderEntry($entry, $entryReport['complete'], false);
				}

				if ((float) $entryReport['cancel'] > 0) {
					$this->stockReservationService->releaseForOrderEntry($entry, $entryReport['cancel'], false);
				}
			}

			$this->entityManager->flush();

			foreach ($stocks as $stock) {
				$stock->setReservedQuantity($this->stockReservationRepository->getActiveQuantityForWarehouseStock($stock));
			}

			$this->entityManager->flush();

			return $this->withoutEntities($report);
		});
	}

	/**
	 * @param OrderEntry[] $entries
	 * @param WarehouseStock[] $stocks
	 *
	 * @return array{apply: bool, entries: list<array<string, mixed>>, stocks: list<array<string, mixed>>}
	 */
	private function buildReport(array $entries, array $stocks, bool $apply): array
	{
		$entryReports = [];

		foreach ($entries as $entry) {
			$active = $this->numberValue($this->stockReservationRepository->getActiveQuantityForOrderEntry($entry));
			$completed = $this->numberValue($this->stockReservationRepository->getQuantityForOrderEntryByStatus($entry, StockReservationStatus::COMPLETED));
			$shipped = $this->numberValue($entry->getShippedQuantity());
			$allowedActive = max(
				0,
				$this->numberValue($entry->getQuantity()) - $shipped - $this->numberValue($entry->getCanceledQuantity())
			);
			$excess = max(0, $active - $allowedActive);
			$complete = min($excess, max(0, $shipped - $completed));
			$cancel = max(0, $excess - $complete);

			if ($complete <= 0.00005 && $cancel <= 0.00005) {
				continue;
			}

			$entryReports[] = [
				'entity' => $entry,
				'order_id' => $entry->getOrder()?->getId() ?? 0,
				'order' => $entry->getOrder()?->getNumber() ?? '',
				'entry_id' => $entry->getId() ?? 0,
				'active' => $this->normalize($active),
				'allowed_active' => $this->normalize($allowedActive),
				'complete' => $this->normalize($complete),
				'cancel' => $this->normalize($cancel),
			];
		}

		$stockReports = [];
		foreach ($stocks as $stock) {
			$actual = $this->normalize($this->stockReservationRepository->getActiveQuantityForWarehouseStock($stock));
			$stored = $this->normalize($stock->getReservedQuantity());
			if (abs((float) $actual - (float) $stored) <= 0.00005) {
				continue;
			}

			$stockReports[] = [
				'entity' => $stock,
				'stock_id' => $stock->getId() ?? 0,
				'stored' => $stored,
				'actual' => $actual,
			];
		}

		return [
			'apply' => $apply,
			'entries' => $entryReports,
			'stocks' => $stockReports,
		];
	}

	/**
	 * @param array{apply: bool, entries: list<array<string, mixed>>, stocks: list<array<string, mixed>>} $report
	 *
	 * @return array{apply: bool, entries: list<array<string, int|string>>, stocks: list<array<string, int|string>>}
	 */
	private function withoutEntities(array $report): array
	{
		foreach ($report['entries'] as &$entryReport) {
			unset($entryReport['entity']);
		}
		unset($entryReport);

		foreach ($report['stocks'] as &$stockReport) {
			unset($stockReport['entity']);
		}
		unset($stockReport);

		return $report;
	}

	private function numberValue(?string $value): float
	{
		return (float) ($value ?? '0');
	}

	private function normalize(string|float|int|null $value): string
	{
		return number_format((float) ($value ?? 0), 4, '.', '');
	}
}

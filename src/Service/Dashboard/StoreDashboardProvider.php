<?php

namespace App\Service\Dashboard;

use App\Entity\Store;
use DateInterval;
use DateTimeImmutable;

final class StoreDashboardProvider
{
	public function __construct(private readonly StoreDashboardQuery $query)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build(Store $store, ?DateTimeImmutable $from, ?DateTimeImmutable $to, ?int $warehouseId, bool $canViewFinancial): array
	{
		$today = new DateTimeImmutable('today');
		$to = $to ?: $today;
		$from = $from ?: $to->sub(new DateInterval('P29D'));

		if ($from > $to) {
			[$from, $to] = [$to, $from];
		}

		$fromStart = $from->setTime(0, 0);
		$toEnd = $to->setTime(23, 59, 59);
		$toExclusive = $to->modify('+1 day')->setTime(0, 0);

		$overview = [
			'ordersCount' => $this->query->countOrders($store, $fromStart, $toExclusive),
			'readyToShipOrders' => $this->query->countOrdersByStatuses($store, ['ready_to_ship']),
			'awaitingStockOrders' => $this->query->countOrdersByStatuses($store, ['awaiting_stock']),
			'purchasesToReceive' => $this->query->countPurchasesByStatuses($store, ['ordered', 'partially_received']),
			'productionInProgress' => $this->query->countProductionByStatuses($store, ['planned', 'materials_reserved', 'in_progress']),
			'draftInventoryDocuments' => $this->query->countInventoryDocumentsByStatus($store, 'draft'),
		];

		if ($canViewFinancial) {
			$salesTotal = $this->query->sumOrders($store, $fromStart, $toExclusive);
			$overview += [
				'salesTotal' => $salesTotal,
				'averageOrderValue' => $overview['ordersCount'] > 0 ? $salesTotal / $overview['ordersCount'] : 0.0,
				'incomingPayments' => $this->query->sumPaymentsByDirection($store, $fromStart, $toExclusive, 'incoming'),
				'outgoingPayments' => $this->query->sumPaymentsByDirection($store, $fromStart, $toExclusive, 'outgoing'),
				'unpaidOrdersTotal' => $this->query->sumUnpaidOrders($store),
				'unpaidPurchasesTotal' => $this->query->sumUnpaidPurchases($store),
			];
			$overview['netCashFlow'] = $overview['incomingPayments'] - $overview['outgoingPayments'];
		}

		$stockSummary = $this->query->stockSummary($store, $warehouseId);
		$criticalStock = $this->query->criticalStock($store, $warehouseId);
		$recentStockMovements = $this->query->recentStockMovements($store, $warehouseId);

		if (!$canViewFinancial) {
			$stockSummary['stockValue'] = null;
			$criticalStock = array_map(
				static fn (array $row): array => array_replace($row, ['averageCost' => null]),
				$criticalStock,
			);
			$recentStockMovements = array_map(
				static fn (array $row): array => array_replace($row, ['unitCost' => null]),
				$recentStockMovements,
			);
		}

		return [
			'range' => [
				'from' => $from->format('Y-m-d'),
				'to' => $to->format('Y-m-d'),
				'fromLabel' => $from->format('d.m.Y'),
				'toLabel' => $to->format('d.m.Y'),
			],
			'warehouseId' => $warehouseId,
			'warehouseChoices' => $this->query->warehouseChoices($store),
			'baseCurrency' => $store->getBaseCurrency()?->getCode(),
			'canViewFinancial' => $canViewFinancial,
			'overview' => $overview,
			'sales' => [
				'chart' => $canViewFinancial ? $this->query->dailyOrderTotals($store, $fromStart, $toExclusive) : ['labels' => [], 'values' => []],
				'statuses' => $this->query->orderStatusCounts($store),
				'topProducts' => $canViewFinancial ? $this->query->topProducts($store, $fromStart, $toExclusive) : [],
				'topCustomers' => $canViewFinancial ? $this->query->topCustomers($store, $fromStart, $toExclusive) : [],
			],
			'stock' => $stockSummary,
			'criticalStock' => $criticalStock,
			'purchases' => [
				'summary' => $canViewFinancial ? $this->query->purchaseSummary($store, $fromStart, $toExclusive) : [],
				'statuses' => $this->query->purchaseStatusCounts($store),
				'topSuppliers' => $canViewFinancial ? $this->query->topSuppliers($store, $fromStart, $toExclusive) : [],
			],
			'production' => [
				'statuses' => $this->query->productionStatusCounts($store),
				'overdue' => $this->query->overdueProduction($store, $today),
				'planVsFact' => $this->query->productionPlanVsFact($store, $fromStart, $toExclusive),
			],
			'payments' => [
				'chart' => $canViewFinancial ? $this->query->dailyPayments($store, $fromStart, $toExclusive) : ['labels' => [], 'incoming' => [], 'outgoing' => []],
				'byType' => $canViewFinancial ? $this->query->paymentsByType($store, $fromStart, $toExclusive) : [],
				'recent' => $canViewFinancial ? $this->query->recentPayments($store) : [],
				'reversed' => $canViewFinancial ? $this->query->reversedPaymentsSummary($store, $fromStart, $toExclusive) : [],
			],
			'control' => [
				'inventoryByType' => $this->query->inventoryDocumentsByType($store, $fromStart, $toExclusive),
				'recentStockMovements' => $recentStockMovements,
				'adjustments' => $this->query->inventoryControlCounts($store, $fromStart, $toExclusive),
			],
		];
	}
}

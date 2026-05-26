<?php

namespace App\Service\Dashboard;

use App\Entity\Store;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class StoreDashboardQuery
{
	public function __construct(private readonly Connection $connection)
	{
	}

	public function countOrders(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): int
	{
		return (int) $this->connection->fetchOne(
			'SELECT COUNT(o.id) FROM orders o WHERE o.store_id = :storeId AND o.created_at >= :fromDate AND o.created_at < :toDate AND o.status <> :canceled',
			$this->dateRangeParams($store, $from, $toExclusive) + ['canceled' => 'canceled'],
		);
	}

	public function sumOrders(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): float
	{
		return (float) $this->connection->fetchOne(
			'SELECT COALESCE(SUM(o.total_amount_base), 0) FROM orders o WHERE o.store_id = :storeId AND o.created_at >= :fromDate AND o.created_at < :toDate AND o.status <> :canceled',
			$this->dateRangeParams($store, $from, $toExclusive) + ['canceled' => 'canceled'],
		);
	}

	public function countOrdersByStatuses(Store $store, array $statuses): int
	{
		return $this->countByStatuses('orders', $store, $statuses);
	}

	public function countPurchasesByStatuses(Store $store, array $statuses): int
	{
		return $this->countByStatuses('purchase', $store, $statuses);
	}

	public function countProductionByStatuses(Store $store, array $statuses): int
	{
		return $this->countByStatuses('production_order', $store, $statuses);
	}

	public function countInventoryDocumentsByStatus(Store $store, string $status): int
	{
		return (int) $this->connection->fetchOne(
			'SELECT COUNT(id) FROM inventory_document WHERE store_id = :storeId AND status = :status',
			['storeId' => $store->getId(), 'status' => $status],
		);
	}

	public function sumPaymentsByDirection(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive, string $direction): float
	{
		return (float) $this->connection->fetchOne(
			'SELECT COALESCE(SUM(p.amount_base), 0)
			 FROM payment p
			 LEFT JOIN payment reversed_by ON reversed_by.reverses_payment_id = p.id
			 WHERE p.store_id = :storeId
			   AND p.paid_at >= :fromDate
			   AND p.paid_at < :toDate
			   AND p.direction = :direction
			   AND p.reverses_payment_id IS NULL
			   AND reversed_by.id IS NULL',
			$this->dateRangeParams($store, $from, $toExclusive) + ['direction' => $direction],
		);
	}

	public function sumUnpaidOrders(Store $store): float
	{
		return (float) $this->connection->fetchOne(
			"SELECT COALESCE(SUM(o.total_amount_base - o.paid_amount_base), 0)
			 FROM orders o
			 WHERE o.store_id = :storeId
			   AND o.status <> 'canceled'
			   AND o.payment_status IN ('unpaid', 'partially_paid')",
			['storeId' => $store->getId()],
		);
	}

	public function sumUnpaidPurchases(Store $store): float
	{
		return (float) $this->connection->fetchOne(
			"SELECT COALESCE(SUM(p.total_amount_base - p.paid_amount_base), 0)
			 FROM purchase p
			 WHERE p.store_id = :storeId
			   AND p.status <> 'canceled'
			   AND p.payment_status IN ('unpaid', 'partially_paid')",
			['storeId' => $store->getId()],
		);
	}

	/**
	 * @return array{labels: list<string>, values: list<float>}
	 */
	public function dailyOrderTotals(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		$rows = $this->connection->fetchAllAssociative(
			"SELECT DATE(o.created_at) AS day, COALESCE(SUM(o.total_amount_base), 0) AS total
			 FROM orders o
			 WHERE o.store_id = :storeId AND o.created_at >= :fromDate AND o.created_at < :toDate AND o.status <> 'canceled'
			 GROUP BY DATE(o.created_at)
			 ORDER BY day ASC",
			$this->dateRangeParams($store, $from, $toExclusive),
		);

		return $this->dailySeries($rows, $from, $toExclusive, 'total');
	}

	/**
	 * @return list<array{status: string, count: int}>
	 */
	public function orderStatusCounts(Store $store): array
	{
		return $this->groupCounts('orders', $store, 'status');
	}

	/**
	 * @return list<array{name: string, quantity: float, total: float}>
	 */
	public function topProducts(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive, int $limit = 10): array
	{
		return array_map(
			static fn (array $row): array => [
				'name' => (string) $row['name'],
				'quantity' => (float) $row['quantity'],
				'total' => (float) $row['total'],
			],
			$this->connection->fetchAllAssociative(
				"SELECT COALESCE(oe.product_name_snapshot, product.name) AS name,
				        COALESCE(SUM(oe.quantity), 0) AS quantity,
				        COALESCE(SUM(oe.total_price_base), 0) AS total
				 FROM order_entry oe
				 INNER JOIN orders o ON o.id = oe.order_id
				 LEFT JOIN product ON product.id = oe.product_id
				 WHERE o.store_id = :storeId AND o.created_at >= :fromDate AND o.created_at < :toDate AND o.status <> 'canceled'
				 GROUP BY COALESCE(oe.product_name_snapshot, product.name)
				 ORDER BY total DESC, quantity DESC
				 LIMIT " . $limit,
				$this->dateRangeParams($store, $from, $toExclusive),
			),
		);
	}

	/**
	 * @return list<array{name: string, orders: int, total: float}>
	 */
	public function topCustomers(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive, int $limit = 10): array
	{
		return array_map(
			static fn (array $row): array => [
				'name' => (string) ($row['name'] ?: 'Unknown customer'),
				'orders' => (int) $row['orders_count'],
				'total' => (float) $row['total'],
			],
			$this->connection->fetchAllAssociative(
				"SELECT COALESCE(o.customer_name_snapshot, customer.name, 'Unknown customer') AS name,
				        COUNT(o.id) AS orders_count,
				        COALESCE(SUM(o.total_amount_base), 0) AS total
				 FROM orders o
				 LEFT JOIN customer ON customer.id = o.customer_id
				 WHERE o.store_id = :storeId AND o.created_at >= :fromDate AND o.created_at < :toDate AND o.status <> 'canceled'
				 GROUP BY COALESCE(o.customer_name_snapshot, customer.name, 'Unknown customer')
				 ORDER BY total DESC, orders_count DESC
				 LIMIT " . $limit,
				$this->dateRangeParams($store, $from, $toExclusive),
			),
		);
	}

	/**
	 * @return array<string, float|int>
	 */
	public function stockSummary(Store $store, ?int $warehouseId): array
	{
		$where = $this->stockWhere($warehouseId);
		$params = $this->stockParams($store, $warehouseId);

		$row = $this->connection->fetchAssociative(
			"SELECT COALESCE(SUM(ws.quantity_on_hand * ws.average_cost), 0) AS stock_value,
			        COALESCE(SUM(ws.quantity_on_hand), 0) AS on_hand,
			        COALESCE(SUM(ws.reserved_quantity), 0) AS reserved,
			        SUM(CASE WHEN ws.quantity_on_hand <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
			        SUM(CASE WHEN (ws.quantity_on_hand - ws.reserved_quantity) < 0 THEN 1 ELSE 0 END) AS negative_available
			 FROM warehouse_stock ws
			 INNER JOIN warehouse w ON w.id = ws.warehouse_id
			 WHERE $where",
			$params,
		) ?: [];

		return [
			'stockValue' => (float) ($row['stock_value'] ?? 0),
			'quantityOnHand' => (float) ($row['on_hand'] ?? 0),
			'reservedQuantity' => (float) ($row['reserved'] ?? 0),
			'outOfStockCount' => (int) ($row['out_of_stock'] ?? 0),
			'negativeAvailableCount' => (int) ($row['negative_available'] ?? 0),
		];
	}

	/**
	 * @return list<array{name: string, warehouse: string, unit: string, onHand: float, reserved: float, available: float, averageCost: float}>
	 */
	public function criticalStock(Store $store, ?int $warehouseId, int $limit = 12): array
	{
		$where = $this->stockWhere($warehouseId);
		$params = $this->stockParams($store, $warehouseId);

		return array_map(
			static fn (array $row): array => [
				'name' => (string) $row['product_name'],
				'warehouse' => (string) $row['warehouse_name'],
				'unit' => (string) ($row['unit_code'] ?? ''),
				'onHand' => (float) $row['quantity_on_hand'],
				'reserved' => (float) $row['reserved_quantity'],
				'available' => (float) $row['available_quantity'],
				'averageCost' => (float) $row['average_cost'],
			],
			$this->connection->fetchAllAssociative(
				"SELECT product.name AS product_name, w.name AS warehouse_name, unit.code AS unit_code,
				        ws.quantity_on_hand, ws.reserved_quantity,
				        (ws.quantity_on_hand - ws.reserved_quantity) AS available_quantity,
				        ws.average_cost
				 FROM warehouse_stock ws
				 INNER JOIN warehouse w ON w.id = ws.warehouse_id
				 INNER JOIN product ON product.id = ws.product_id
				 LEFT JOIN unit ON unit.id = product.unit_id
				 WHERE $where
				   AND (ws.quantity_on_hand <= 0 OR (ws.quantity_on_hand - ws.reserved_quantity) <= 0 OR ws.reserved_quantity > 0)
				 ORDER BY available_quantity ASC, ws.reserved_quantity DESC
				 LIMIT " . $limit,
				$params,
			),
		);
	}

	/**
	 * @return array<string, float|int>
	 */
	public function purchaseSummary(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		$row = $this->connection->fetchAssociative(
			"SELECT COUNT(id) AS purchases_count,
			        COALESCE(SUM(total_amount_base), 0) AS total
			 FROM purchase
			 WHERE store_id = :storeId AND created_at >= :fromDate AND created_at < :toDate AND status <> 'canceled'",
			$this->dateRangeParams($store, $from, $toExclusive),
		) ?: [];

		return [
			'purchasesCount' => (int) ($row['purchases_count'] ?? 0),
			'total' => (float) ($row['total'] ?? 0),
		];
	}

	/**
	 * @return list<array{status: string, count: int}>
	 */
	public function purchaseStatusCounts(Store $store): array
	{
		return $this->groupCounts('purchase', $store, 'status');
	}

	/**
	 * @return list<array{name: string, purchases: int, total: float}>
	 */
	public function topSuppliers(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive, int $limit = 10): array
	{
		return array_map(
			static fn (array $row): array => [
				'name' => (string) ($row['name'] ?: 'Unknown supplier'),
				'purchases' => (int) $row['purchases_count'],
				'total' => (float) $row['total'],
			],
			$this->connection->fetchAllAssociative(
				"SELECT COALESCE(p.supplier_name_snapshot, supplier.name, 'Unknown supplier') AS name,
				        COUNT(p.id) AS purchases_count,
				        COALESCE(SUM(p.total_amount_base), 0) AS total
				 FROM purchase p
				 LEFT JOIN supplier ON supplier.id = p.supplier_id
				 WHERE p.store_id = :storeId AND p.created_at >= :fromDate AND p.created_at < :toDate AND p.status <> 'canceled'
				 GROUP BY COALESCE(p.supplier_name_snapshot, supplier.name, 'Unknown supplier')
				 ORDER BY total DESC, purchases_count DESC
				 LIMIT " . $limit,
				$this->dateRangeParams($store, $from, $toExclusive),
			),
		);
	}

	/**
	 * @return list<array{status: string, count: int}>
	 */
	public function productionStatusCounts(Store $store): array
	{
		return $this->groupCounts('production_order', $store, 'status');
	}

	public function overdueProduction(Store $store, DateTimeImmutable $today): int
	{
		return (int) $this->connection->fetchOne(
			"SELECT COUNT(id)
			 FROM production_order
			 WHERE store_id = :storeId
			   AND planned_end_at IS NOT NULL
			   AND planned_end_at < :today
			   AND status NOT IN ('completed', 'canceled')",
			['storeId' => $store->getId(), 'today' => $today->format('Y-m-d 00:00:00')],
		);
	}

	/**
	 * @return array{planned: float, completed: float}
	 */
	public function productionPlanVsFact(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		$row = $this->connection->fetchAssociative(
			'SELECT COALESCE(SUM(planned_quantity), 0) AS planned, COALESCE(SUM(completed_quantity), 0) AS completed
			 FROM production_order
			 WHERE store_id = :storeId AND created_at >= :fromDate AND created_at < :toDate',
			$this->dateRangeParams($store, $from, $toExclusive),
		) ?: [];

		return [
			'planned' => (float) ($row['planned'] ?? 0),
			'completed' => (float) ($row['completed'] ?? 0),
		];
	}

	/**
	 * @return array{labels: list<string>, incoming: list<float>, outgoing: list<float>}
	 */
	public function dailyPayments(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		$rows = $this->connection->fetchAllAssociative(
			"SELECT DATE(p.paid_at) AS day, p.direction, COALESCE(SUM(p.amount_base), 0) AS total
			 FROM payment p
			 LEFT JOIN payment reversed_by ON reversed_by.reverses_payment_id = p.id
			 WHERE p.store_id = :storeId
			   AND p.paid_at >= :fromDate
			   AND p.paid_at < :toDate
			   AND p.reverses_payment_id IS NULL
			   AND reversed_by.id IS NULL
			 GROUP BY DATE(p.paid_at), p.direction
			 ORDER BY day ASC",
			$this->dateRangeParams($store, $from, $toExclusive),
		);

		$labels = [];
		$incoming = [];
		$outgoing = [];
		$cursor = $from;
		while ($cursor < $toExclusive) {
			$key = $cursor->format('Y-m-d');
			$labels[] = $key;
			$incoming[$key] = 0.0;
			$outgoing[$key] = 0.0;
			$cursor = $cursor->modify('+1 day');
		}

		foreach ($rows as $row) {
			$key = (string) $row['day'];
			if ($row['direction'] === 'incoming' && array_key_exists($key, $incoming)) {
				$incoming[$key] = (float) $row['total'];
			}
			if ($row['direction'] === 'outgoing' && array_key_exists($key, $outgoing)) {
				$outgoing[$key] = (float) $row['total'];
			}
		}

		return ['labels' => $labels, 'incoming' => array_values($incoming), 'outgoing' => array_values($outgoing)];
	}

	/**
	 * @return list<array{type: string, direction: string, total: float}>
	 */
	public function paymentsByType(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		return array_map(
			static fn (array $row): array => [
				'type' => (string) $row['type'],
				'direction' => (string) $row['direction'],
				'total' => (float) $row['total'],
			],
			$this->connection->fetchAllAssociative(
				"SELECT p.type, p.direction, COALESCE(SUM(p.amount_base), 0) AS total
				 FROM payment p
				 LEFT JOIN payment reversed_by ON reversed_by.reverses_payment_id = p.id
				 WHERE p.store_id = :storeId
				   AND p.paid_at >= :fromDate
				   AND p.paid_at < :toDate
				   AND p.reverses_payment_id IS NULL
				   AND reversed_by.id IS NULL
				 GROUP BY p.type, p.direction
				 ORDER BY p.direction ASC, total DESC",
				$this->dateRangeParams($store, $from, $toExclusive),
			),
		);
	}

	/**
	 * @return list<array{paidAt: string, direction: string, type: string, amount: float, currency: string, document: string}>
	 */
	public function recentPayments(Store $store, int $limit = 8): array
	{
		return array_map(
			static fn (array $row): array => [
				'paidAt' => substr((string) $row['paid_at'], 0, 10),
				'direction' => (string) $row['direction'],
				'type' => (string) $row['type'],
				'amount' => (float) $row['amount_base'],
				'currency' => (string) $row['currency_code'],
				'document' => (string) ($row['order_number'] ?: $row['purchase_number'] ?: ''),
			],
			$this->connection->fetchAllAssociative(
				"SELECT p.paid_at, p.direction, p.type, p.amount_base, p.currency_code, o.number AS order_number, purchase.number AS purchase_number
				 FROM payment p
				 LEFT JOIN orders o ON o.id = p.order_id
				 LEFT JOIN purchase ON purchase.id = p.purchase_id
				 WHERE p.store_id = :storeId
				 ORDER BY p.paid_at DESC, p.id DESC
				 LIMIT " . $limit,
				['storeId' => $store->getId()],
			),
		);
	}

	/**
	 * @return array{count: int, total: float}
	 */
	public function reversedPaymentsSummary(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		$row = $this->connection->fetchAssociative(
			'SELECT COUNT(p.id) AS payments_count, COALESCE(SUM(p.amount_base), 0) AS total
			 FROM payment p
			 WHERE p.store_id = :storeId
			   AND p.paid_at >= :fromDate
			   AND p.paid_at < :toDate
			   AND p.reverses_payment_id IS NOT NULL',
			$this->dateRangeParams($store, $from, $toExclusive),
		) ?: [];

		return ['count' => (int) ($row['payments_count'] ?? 0), 'total' => (float) ($row['total'] ?? 0)];
	}

	/**
	 * @return list<array{type: string, status: string, count: int}>
	 */
	public function inventoryDocumentsByType(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		return array_map(
			static fn (array $row): array => [
				'type' => (string) $row['type'],
				'status' => (string) $row['status'],
				'count' => (int) $row['documents_count'],
			],
			$this->connection->fetchAllAssociative(
				'SELECT type, status, COUNT(id) AS documents_count
				 FROM inventory_document
				 WHERE store_id = :storeId AND document_date >= :fromDate AND document_date < :toDate
				 GROUP BY type, status
				 ORDER BY type ASC, status ASC',
				$this->dateRangeParams($store, $from, $toExclusive),
			),
		);
	}

	/**
	 * @return list<array{createdAt: string, product: string, warehouse: string, quantity: float, unitCost: float, document: string}>
	 */
	public function recentStockMovements(Store $store, ?int $warehouseId, int $limit = 10): array
	{
		$where = 'document.store_id = :storeId';
		$params = ['storeId' => $store->getId()];
		if ($warehouseId) {
			$where .= ' AND line.warehouse_id = :warehouseId';
			$params['warehouseId'] = $warehouseId;
		}

		return array_map(
			static fn (array $row): array => [
				'createdAt' => substr((string) $row['created_at'], 0, 16),
				'product' => (string) $row['product_name'],
				'warehouse' => (string) $row['warehouse_name'],
				'quantity' => (float) $row['quantity_change'],
				'unitCost' => (float) $row['unit_cost'],
				'document' => (string) $row['number'],
			],
			$this->connection->fetchAllAssociative(
				"SELECT movement.created_at, product.name AS product_name, warehouse.name AS warehouse_name,
				        movement.quantity_change, movement.unit_cost, document.number
				 FROM stock_movement movement
				 INNER JOIN inventory_document_line line ON line.id = movement.inventory_document_line_id
				 INNER JOIN inventory_document document ON document.id = line.inventory_document_id
				 INNER JOIN product ON product.id = line.product_id
				 INNER JOIN warehouse ON warehouse.id = line.warehouse_id
				 WHERE $where
				 ORDER BY movement.created_at DESC, movement.id DESC
				 LIMIT " . $limit,
				$params,
			),
		);
	}

	/**
	 * @return array<string, int>
	 */
	public function inventoryControlCounts(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		$rows = $this->connection->fetchAllAssociative(
			"SELECT type, COUNT(id) AS documents_count
			 FROM inventory_document
			 WHERE store_id = :storeId
			   AND document_date >= :fromDate
			   AND document_date < :toDate
			   AND type IN ('write_off', 'stock_adjustment', 'customer_return', 'supplier_return', 'reversal')
			 GROUP BY type",
			$this->dateRangeParams($store, $from, $toExclusive),
		);

		$result = [
			'write_off' => 0,
			'stock_adjustment' => 0,
			'customer_return' => 0,
			'supplier_return' => 0,
			'reversal' => 0,
		];
		foreach ($rows as $row) {
			$result[(string) $row['type']] = (int) $row['documents_count'];
		}

		return $result;
	}

	/**
	 * @return list<array{id: int, name: string}>
	 */
	public function warehouseChoices(Store $store): array
	{
		return array_map(
			static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']],
			$this->connection->fetchAllAssociative(
				'SELECT id, name FROM warehouse WHERE store_id = :storeId AND deleted_at IS NULL ORDER BY name ASC',
				['storeId' => $store->getId()],
			),
		);
	}

	private function countByStatuses(string $table, Store $store, array $statuses): int
	{
		if ($statuses === []) {
			return 0;
		}

		$placeholders = [];
		$params = ['storeId' => $store->getId()];
		foreach (array_values($statuses) as $index => $status) {
			$key = 'status' . $index;
			$placeholders[] = ':' . $key;
			$params[$key] = $status;
		}

		return (int) $this->connection->fetchOne(
			sprintf('SELECT COUNT(id) FROM %s WHERE store_id = :storeId AND status IN (%s)', $table, implode(', ', $placeholders)),
			$params,
		);
	}

	/**
	 * @return list<array{status: string, count: int}>
	 */
	private function groupCounts(string $table, Store $store, string $column): array
	{
		return array_map(
			static fn (array $row): array => ['status' => (string) $row[$column], 'count' => (int) $row['rows_count']],
			$this->connection->fetchAllAssociative(
				sprintf('SELECT %s, COUNT(id) AS rows_count FROM %s WHERE store_id = :storeId GROUP BY %s ORDER BY rows_count DESC', $column, $table, $column),
				['storeId' => $store->getId()],
			),
		);
	}

	/**
	 * @return array{storeId: int|null, fromDate: string, toDate: string}
	 */
	private function dateRangeParams(Store $store, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
	{
		return [
			'storeId' => $store->getId(),
			'fromDate' => $from->format('Y-m-d H:i:s'),
			'toDate' => $toExclusive->format('Y-m-d H:i:s'),
		];
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array{labels: list<string>, values: list<float>}
	 */
	private function dailySeries(array $rows, DateTimeImmutable $from, DateTimeImmutable $toExclusive, string $valueKey): array
	{
		$values = [];
		$cursor = $from;
		while ($cursor < $toExclusive) {
			$values[$cursor->format('Y-m-d')] = 0.0;
			$cursor = $cursor->modify('+1 day');
		}

		foreach ($rows as $row) {
			$key = (string) $row['day'];
			if (array_key_exists($key, $values)) {
				$values[$key] = (float) $row[$valueKey];
			}
		}

		return ['labels' => array_keys($values), 'values' => array_values($values)];
	}

	private function stockWhere(?int $warehouseId): string
	{
		return 'w.store_id = :storeId' . ($warehouseId ? ' AND w.id = :warehouseId' : '');
	}

	/**
	 * @return array<string, int|null>
	 */
	private function stockParams(Store $store, ?int $warehouseId): array
	{
		$params = ['storeId' => $store->getId()];
		if ($warehouseId) {
			$params['warehouseId'] = $warehouseId;
		}

		return $params;
	}
}

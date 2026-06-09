# Stock reservation reconciler

`StockReservationReconciler` repairs historical reservation drift without changing valid open-order demand.

## Rules

- Active reservation quantity allowed for an order entry equals `quantity - shippedQuantity - canceledQuantity`.
- Active excess that corresponds to shipped quantity and is not already represented by completed reservations is completed.
- Remaining active excess is canceled.
- `WarehouseStock.reservedQuantity` is synchronized to the sum of active reservation rows.
- Dry-run mode does not mutate data. Apply mode runs inside a transaction and locks affected orders and warehouse stocks.

## Command

Dry-run is the default:

```bash
php bin/console app:stock-reservations:reconcile
php bin/console app:stock-reservations:reconcile --store-id=5
php bin/console app:stock-reservations:reconcile --order-id=11
```

Apply the reported changes:

```bash
php bin/console app:stock-reservations:reconcile --store-id=5 --apply
```

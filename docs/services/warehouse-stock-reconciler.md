# Warehouse stock reconciler

`WarehouseStockReconciler` detects and repairs historical drift between stock movements, warehouse stock aggregates, and batch remaining quantities.

## Source of truth

- Posted `StockMovement.quantityChange` rows are immutable history and are not rewritten.
- The opening quantity is inferred from the first movement as `balanceAfter - quantityChange`.
- Movement `balanceAfter` values are recalculated in posting order.
- Opening batches are replayed FIFO. Excess synthetic opening layers are retained with zero remaining quantity.
- Incoming movements add quantity to their linked batch. Legacy unlinked incoming movements add to the oldest active layer without changing historical links.
- Outgoing movements consume their linked batch first and then fall back to FIFO.
- Unsafe replay, including a negative stock balance or an unallocatable outgoing movement, is reported and is not applied.

## Command

Dry-run is the default:

```bash
php bin/console app:warehouse-stock:reconcile
php bin/console app:warehouse-stock:reconcile --store-id=5
php bin/console app:warehouse-stock:reconcile --stock-id=67
```

Apply safe reported changes:

```bash
php bin/console app:warehouse-stock:reconcile --store-id=5 --apply
```

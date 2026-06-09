# Concurrency control

This project uses two layers of protection for multi-manager workflows.

## Row locks for business actions

State-changing actions must run inside a Doctrine transaction and lock the current database row before checking status or quantities.

Use `App\Service\Concurrency\ConcurrencyGuard` for:

- status actions on orders, purchases, production orders, and inventory documents;
- inventory posting/canceling and stock batch consumption;
- stock reservations and releases;
- payment creation/reversal and payment-status recalculation.

The lock must happen before calling workflow transitions, posting stock movements, recalculating progress, or recalculating payment status.

## Optimistic form protection

Editable business documents expose a hidden `version` field. On submit, controllers compare it with the current entity version before saving.

If the versions differ, the controller must reject the save with:

`Document was changed by another user. Refresh and try again.`

This protects draft forms from silent last-write-wins overwrites while avoiding long edit locks.

## Rules for future changes

- Do not mutate `WarehouseStock.quantityOnHand`, `WarehouseStock.reservedQuantity`, or `WarehouseStockBatch.remainingQuantity` without a transaction and row lock.
- Do not add a new status action without locking the subject first.
- Do not recalculate order/purchase payment state without locking the target document.
- Do not create shipment/receipt drafts for an order or purchase if an existing draft of the same operation is still open.
- Allocate completed linked production after the production transaction commits. The replenishment subscriber must lock the sales order before reserving output stock, then run the general FIFO replenishment queue.

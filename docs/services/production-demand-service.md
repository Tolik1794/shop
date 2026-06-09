# Production demand service

`ProductionDemandService` connects make-to-order sales positions with production orders.

## Confirmation flow

- An order entry explicitly selects `stock`, `production`, or `service` fulfillment.
- A draft production entry may remain incomplete. Confirmation requires an output warehouse, a manufacturable product, and an active default recipe.
- Confirmation validates production demand before opening the write transaction, so configuration errors leave the order in draft and return a user-safe quick-action response.
- Furniture Works demo recipes can be appended without purging business data:
  `php bin/console doctrine:fixtures:load --group=production_recipes --append --no-interaction`.
- Order confirmation creates one linked production order per production entry and immediately plans it.
- Stock reservations for production entries are accepted only from their linked completed production, so unrelated stock cannot silently fulfill the position.

## Completion and demand changes

- Completing linked production posts the normal `production` inventory document.
- Produced stock is reserved for the source order entry before the general awaiting-stock FIFO queue runs.
- Partial completion creates a new planned production order for the uncovered quantity.
- Order cancellation or refusal cancels/replaces only draft or planned linked production. Reserved or started production is preserved and any excess output becomes general stock.

## Concurrency

Order confirmation, legacy demand planning, refusal, cancellation, and production completion run inside existing transactions and lock their parent business documents. The partial unique index on `production_order.source_order_entry_id` prevents duplicate active linked production orders.

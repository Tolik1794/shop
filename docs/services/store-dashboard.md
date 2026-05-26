# Store Dashboard Analytics Service

The store dashboard is a read-only reporting surface for one selected store.

## Responsibilities

- `StoreDashboardProvider` builds the final view model for the dashboard page.
- `StoreDashboardQuery` contains aggregate read queries for orders, payments, stock, purchases, production, and inventory documents.
- Controllers only parse request filters, check store access, and pass the view model to Twig.

## Reporting Rules

- All monetary totals are reported in the store base currency using persisted `*Base` document fields.
- Historical order and purchase totals are not recalculated from current product prices.
- Stock value uses the current warehouse stock quantity and average cost.
- Reversed payments are separated from normal incoming/outgoing cash-flow totals.
- Store managers without `ROLE_STORE_ADMIN` receive operational counts without financial totals.

## Filters

The dashboard accepts GET filters:

- `from` - start date in `YYYY-MM-DD`.
- `to` - end date in `YYYY-MM-DD`.
- `warehouse` - optional warehouse id for stock and movement sections.

Invalid or missing dates fall back to the last 30 days.

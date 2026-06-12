# RBAC and permissions

Authorization is based on permission codes, not hard-coded business roles.

Core concepts:

- `Permission` stores stable permission codes such as `dashboard.view`, `order.edit`, `warehouse_stock.manage`.
- `UserGroup` stores global groups such as Administrator, Store Administrator, Manager, Stockkeeper.
- `UserGroup.permissions` grants many permissions to a group.
- `User.groups` assigns a user to many groups.
- `UserPermissionOverride` stores per-user allow or deny overrides.

Resolution order:

1. A per-user deny for the exact permission returns false.
2. A per-user allow for the exact permission returns true.
3. Group permissions are checked.
4. `system.all` grants all known permissions unless the user has an exact deny override.

Symfony integration:

- Page and action access uses `#[IsGranted('permission.code')]`.
- Twig visibility uses `is_granted('permission.code')`.
- Store and user object checks use voters with object-aware permissions such as `store.edit` and `user.edit`.
- `AdminStoreAccessSubscriber` protects admin/API routes with `store_id` by checking `store.view` against the concrete store object.
- Services should use `PermissionChecker` when a business action needs a permission check outside HTTP controllers.
- Product discount settings are protected by `product_discount.view` and `product_discount.manage`.
- Manual order discounts and below-cost discounted order lines require `order.discount.override`; normal order editors can only use configured product/category discount rules.
- Tax module pages (income control, accruals, declaration drafts) require `tax.view` to read. Write actions are split: `tax.income.manage` for manual income entry, reclassification and accrual payment; `tax.reports.manage` for generating declarations and managing period lifecycle; `tax.settings.manage` for legal entities and rate sets.

The legacy `users.roles` JSON column remains only for compatibility with existing data and tests. New authorization decisions should use permission codes and groups.

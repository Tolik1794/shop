<?php

namespace App\Security;

final class PermissionCatalog
{
	public const string SYSTEM_ALL = 'system.all';

	public const array PERMISSIONS = [
		self::SYSTEM_ALL => ['System', 'Full system access', 'Allows every permission unless an individual deny override exists.'],
		'rbac.view' => ['Security', 'View groups and permissions', 'Can view RBAC configuration.'],
		'rbac.manage' => ['Security', 'Manage groups and permissions', 'Can manage user groups and permission assignments.'],
		'user.view' => ['Users', 'View users', 'Can open user lists and user profiles.'],
		'user.edit' => ['Users', 'Edit users', 'Can edit user store assignments, groups and permission overrides.'],
		'store.view' => ['Stores', 'View stores', 'Can view assigned stores.'],
		'store.view_all' => ['Stores', 'View all stores', 'Can view every store, ignoring store manager assignment.'],
		'store.create' => ['Stores', 'Create stores', 'Can create new stores.'],
		'store.edit' => ['Stores', 'Edit stores', 'Can edit assigned stores.'],
		'dashboard.view' => ['Dashboard', 'View dashboard', 'Can view store analytics dashboard.'],
		'dashboard.financial' => ['Dashboard', 'View financial analytics', 'Can view revenue, cost and margin analytics.'],
		'order.view' => ['Orders', 'View orders', 'Can view sales orders.'],
		'order.create' => ['Orders', 'Create orders', 'Can create sales orders.'],
		'order.edit' => ['Orders', 'Edit orders', 'Can edit sales orders.'],
		'order.confirm' => ['Orders', 'Confirm orders', 'Can confirm sales orders.'],
		'order.cancel' => ['Orders', 'Cancel orders', 'Can cancel sales orders.'],
		'order.rollback' => ['Orders', 'Rollback orders', 'Can rollback order status.'],
		'order.ship' => ['Orders', 'Ship orders', 'Can ship order lines.'],
		'order.mark_delivered' => ['Orders', 'Mark orders delivered', 'Can mark shipped orders as delivered.'],
		'order.complete' => ['Orders', 'Complete orders', 'Can complete delivered orders.'],
		'order.comment.manage' => ['Orders', 'Manage order comments', 'Can create and manage order comments.'],
		'purchase.view' => ['Purchases', 'View purchases', 'Can view purchases.'],
		'purchase.create' => ['Purchases', 'Create purchases', 'Can create purchases.'],
		'purchase.edit' => ['Purchases', 'Edit purchases', 'Can edit purchases.'],
		'purchase.order' => ['Purchases', 'Order purchases', 'Can mark purchases as ordered.'],
		'purchase.receive' => ['Purchases', 'Receive purchases', 'Can receive purchased stock.'],
		'purchase.complete' => ['Purchases', 'Complete purchases', 'Can complete purchases.'],
		'purchase.cancel' => ['Purchases', 'Cancel purchases', 'Can cancel purchases.'],
		'payment.view' => ['Payments', 'View payments', 'Can view payments.'],
		'payment.create' => ['Payments', 'Create payments', 'Can create payments.'],
		'payment.reverse' => ['Payments', 'Reverse payments', 'Can reverse payments.'],
		'warehouse.view' => ['Warehouse', 'View warehouses', 'Can view warehouses.'],
		'warehouse.manage' => ['Warehouse', 'Manage warehouses', 'Can create and edit warehouses.'],
		'warehouse_stock.view' => ['Warehouse', 'View stock', 'Can view warehouse stock.'],
		'warehouse_stock.manage' => ['Warehouse', 'Manage stock', 'Can edit warehouse stock.'],
		'inventory_document.view' => ['Inventory', 'View inventory documents', 'Can view stock adjustment, movement and return documents.'],
		'inventory_document.create' => ['Inventory', 'Create inventory documents', 'Can create inventory documents.'],
		'inventory_document.post' => ['Inventory', 'Post inventory documents', 'Can post inventory documents to stock.'],
		'inventory_document.cancel' => ['Inventory', 'Cancel inventory documents', 'Can cancel inventory documents.'],
		'inventory_reason.manage' => ['Inventory', 'Manage inventory reasons', 'Can manage inventory reason dictionary.'],
		'category.manage' => ['Catalog', 'Manage categories', 'Can manage product categories.'],
		'category_parameter.manage' => ['Catalog', 'Manage category parameters', 'Can manage category parameter dictionary.'],
		'product.view' => ['Catalog', 'View products', 'Can view products.'],
		'product.manage' => ['Catalog', 'Manage products', 'Can create and edit products.'],
		'product_price.manage' => ['Catalog', 'Manage product prices', 'Can manage product prices.'],
		'unit.manage' => ['Catalog', 'Manage units', 'Can manage units.'],
		'exchange_rate.manage' => ['Settings', 'Manage exchange rates', 'Can manage exchange rates.'],
		'customer.view' => ['Parties', 'View customers', 'Can view customers.'],
		'customer.manage' => ['Parties', 'Manage customers', 'Can manage customers.'],
		'supplier.view' => ['Parties', 'View suppliers', 'Can view suppliers.'],
		'supplier.manage' => ['Parties', 'Manage suppliers', 'Can manage suppliers.'],
		'production_recipe.view' => ['Production', 'View production recipes', 'Can view production recipes.'],
		'production_recipe.manage' => ['Production', 'Manage production recipes', 'Can manage production recipes.'],
		'production_order.view' => ['Production', 'View production orders', 'Can view production orders.'],
		'production_order.create' => ['Production', 'Create production orders', 'Can create production orders.'],
		'production_order.edit' => ['Production', 'Edit production orders', 'Can edit production orders.'],
		'production_order.plan' => ['Production', 'Plan production orders', 'Can plan production orders.'],
		'production_order.reserve_materials' => ['Production', 'Reserve materials', 'Can reserve production materials.'],
		'production_order.start' => ['Production', 'Start production', 'Can start production orders.'],
		'production_order.complete' => ['Production', 'Complete production', 'Can complete production orders.'],
		'production_order.cancel' => ['Production', 'Cancel production', 'Can cancel production orders.'],
	];

	private const array GROUP_PERMISSIONS = [
		'super_admin' => [self::SYSTEM_ALL],
		'admin' => [
			'rbac.view', 'rbac.manage', 'user.view', 'user.edit',
			'store.view', 'store.view_all', 'store.create', 'store.edit',
			'dashboard.view', 'dashboard.financial',
			'order.view', 'order.create', 'order.edit', 'order.confirm', 'order.cancel', 'order.rollback', 'order.ship', 'order.mark_delivered', 'order.complete', 'order.comment.manage',
			'purchase.view', 'purchase.create', 'purchase.edit', 'purchase.order', 'purchase.receive', 'purchase.complete', 'purchase.cancel',
			'payment.view', 'payment.create', 'payment.reverse',
			'warehouse.view', 'warehouse.manage', 'warehouse_stock.view', 'warehouse_stock.manage',
			'inventory_document.view', 'inventory_document.create', 'inventory_document.post', 'inventory_document.cancel', 'inventory_reason.manage',
			'category.manage', 'category_parameter.manage', 'product.view', 'product.manage', 'product_price.manage', 'unit.manage', 'exchange_rate.manage',
			'customer.view', 'customer.manage', 'supplier.view', 'supplier.manage',
			'production_recipe.view', 'production_recipe.manage', 'production_order.view', 'production_order.create', 'production_order.edit', 'production_order.plan', 'production_order.reserve_materials', 'production_order.start', 'production_order.complete', 'production_order.cancel',
		],
		'store_admin' => [
			'user.view', 'user.edit',
			'store.view', 'store.edit',
			'dashboard.view', 'dashboard.financial',
			'order.view', 'order.create', 'order.edit', 'order.confirm', 'order.cancel', 'order.rollback', 'order.ship', 'order.mark_delivered', 'order.complete', 'order.comment.manage',
			'purchase.view', 'purchase.create', 'purchase.edit', 'purchase.order', 'purchase.receive', 'purchase.complete', 'purchase.cancel',
			'payment.view', 'payment.create', 'payment.reverse',
			'warehouse.view', 'warehouse.manage', 'warehouse_stock.view', 'warehouse_stock.manage',
			'inventory_document.view', 'inventory_document.create', 'inventory_document.post', 'inventory_document.cancel', 'inventory_reason.manage',
			'category.manage', 'category_parameter.manage', 'product.view', 'product.manage', 'product_price.manage', 'unit.manage', 'exchange_rate.manage',
			'customer.view', 'customer.manage', 'supplier.view', 'supplier.manage',
			'production_recipe.view', 'production_recipe.manage', 'production_order.view', 'production_order.create', 'production_order.edit', 'production_order.plan', 'production_order.reserve_materials', 'production_order.start', 'production_order.complete', 'production_order.cancel',
		],
		'manager' => [
			'store.view', 'dashboard.view',
			'order.view', 'order.create', 'order.edit', 'order.confirm', 'order.cancel', 'order.rollback', 'order.comment.manage',
			'purchase.view', 'purchase.create', 'purchase.edit', 'purchase.order', 'purchase.cancel',
			'payment.view', 'payment.create',
			'warehouse.view', 'warehouse_stock.view',
			'customer.view', 'customer.manage', 'supplier.view', 'supplier.manage',
			'product.view',
			'production_recipe.view', 'production_order.view', 'production_order.create', 'production_order.edit', 'production_order.plan',
		],
		'stockkeeper' => [
			'store.view', 'dashboard.view',
			'warehouse.view', 'warehouse_stock.view', 'warehouse_stock.manage',
			'inventory_document.view', 'inventory_document.create', 'inventory_document.post', 'inventory_document.cancel',
			'purchase.view', 'purchase.receive',
			'order.view', 'order.ship', 'order.mark_delivered',
			'product.view',
			'production_recipe.view', 'production_order.view', 'production_order.reserve_materials', 'production_order.start', 'production_order.complete',
		],
		'user' => ['store.view', 'dashboard.view'],
	];

	public const array GROUPS = [
		'super_admin' => ['Super Administrator', 'Full system access.', true],
		'admin' => ['Administrator', 'Global administrator without the system.all bypass.', true],
		'store_admin' => ['Store Administrator', 'Can manage assigned stores and store operations.', true],
		'manager' => ['Manager', 'Can run daily sales, purchase and party workflows.', true],
		'stockkeeper' => ['Stockkeeper', 'Can manage stock, warehouse and inventory workflows.', true],
		'user' => ['User', 'Minimal dashboard access for assigned stores.', true],
	];

	private const array LEGACY_ROLE_GROUPS = [
		'ROLE_SUPER_ADMIN' => ['super_admin'],
		'ROLE_ADMIN' => ['admin'],
		'ROLE_STORE_ADMIN' => ['store_admin'],
		'ROLE_STORE_MANAGER' => ['manager'],
		'ROLE_USER' => ['user'],
	];

	public function permissions(): array
	{
		return self::PERMISSIONS;
	}

	public function has(string $permissionCode): bool
	{
		return isset(self::PERMISSIONS[$permissionCode]);
	}

	public function groupPermissions(string $groupCode): array
	{
		return self::GROUP_PERMISSIONS[$groupCode] ?? [];
	}

	public function legacyRoleGroupCodes(array $roles): array
	{
		$groupCodes = [];

		foreach ($roles as $role) {
			foreach (self::LEGACY_ROLE_GROUPS[$role] ?? [] as $groupCode) {
				$groupCodes[] = $groupCode;
			}
		}

		return array_values(array_unique($groupCodes));
	}
}

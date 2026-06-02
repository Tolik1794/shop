<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MenuBuilder
{
	public function __construct(
		private readonly FactoryInterface $factory,
		private readonly RequestStack $requestStack,
		private readonly TranslatorInterface $translator,
		private readonly AuthorizationCheckerInterface $authorizationChecker,
	)
	{
	}

	public function mainAdminMenu(array $options): ItemInterface
	{
		$menu = $this->factory->createItem('mainAdmin', [
			'childrenAttributes' => [
				'class' => 'sidebar-nav',
			],
		]);

		$this->addSidebarLink($menu, 'store.view', 'admin.menu.stores', 'admin_store_index', $this->routeParameters(), 'fa-store');
		$this->addSidebarLink($menu, 'user.view', 'admin.menu.managers', 'admin_user_index', $this->routeParameters(), 'fa-users');
		$this->addSidebarLink($menu, 'rbac.view', 'admin.menu.access_groups', 'admin_user_group_index', $this->routeParameters(), 'fa-user-shield');

		return $menu;
	}

	public function mainAdminStoreMenu(array $options): ItemInterface
	{
		$storeId = $this->requestStack->getCurrentRequest()?->attributes->get('store_id');
		$routeParameters = $this->routeParameters(['store_id' => $storeId]);

		$menu = $this->factory->createItem('mainAdminStore', [
			'childrenAttributes' => [
				'class' => 'sidebar-nav',
			],
		]);

		$this->addSidebarLink($menu, 'dashboard.view', 'admin.menu.dashboard', 'admin_store_main', $routeParameters, 'fa-gauge-high');
		$this->addSidebarLink($menu, 'order.view', 'admin.menu.orders', 'app_admin_order_index', $routeParameters, 'fa-cart-shopping');
		$this->addSidebarLink($menu, 'purchase.view', 'admin.menu.purchases', 'app_admin_purchase_index', $routeParameters, 'fa-basket-shopping');
		$this->addSidebarLink($menu, 'production_order.view', 'admin.menu.production', 'app_admin_production_order_index', $routeParameters, 'fa-industry');
		$this->addSidebarLink($menu, 'payment.view', 'admin.menu.payments', 'app_admin_payment_index', $routeParameters, 'fa-money-bill-wave');
		$this->addSidebarLink($menu, 'inventory_document.view', 'admin.menu.inventory_documents', 'app_admin_inventory_document_index', $routeParameters, 'fa-clipboard-list');
		$this->addSidebarLink($menu, 'customer.view', 'admin.menu.customers', 'app_admin_customer_index', $routeParameters, 'fa-address-book');
		$this->addSidebarLink($menu, 'supplier.view', 'admin.menu.suppliers', 'app_admin_supplier_index', $routeParameters, 'fa-truck');

		$settings = $this->factory->createItem($this->trans('admin.menu.settings'), [
			'uri' => '#',
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'data-bs-target' => '#setting',
				'data-bs-toggle' => 'collapse',
				'aria-expanded' => 'false',
				'class' => 'sidebar-link collapsed',
			],
			'childrenAttributes' => [
				'class' => 'sidebar-dropdown list-unstyled collapse',
				'id' => 'setting',
				'data-bs-parent' => 'sidebar',
			],
		]);
		$settings->setExtra('icon', 'fa-gears');

		$this->addSidebarLink($settings, 'category.manage', 'admin.menu.categories', 'admin_category_index', $routeParameters, 'fa-layer-group');
		$this->addSidebarLink($settings, 'product.view', 'admin.menu.products', 'admin_product_index', $routeParameters, 'fa-box-open');
		$this->addSidebarLink($settings, 'product_discount.view', 'admin.menu.discounts', 'app_admin_product_discount_index', $routeParameters, 'fa-percent');
		$this->addSidebarLink($settings, 'unit.manage', 'admin.menu.units', 'app_admin_unit_index', $routeParameters, 'fa-ruler-combined');
		$this->addSidebarLink($settings, 'customer_label.view', 'admin.menu.customer_labels', 'app_admin_customer_label_index', $routeParameters, 'fa-tags');
		$this->addSidebarLink($settings, 'warehouse.view', 'admin.menu.warehouses', 'app_admin_warehouse_index', $routeParameters, 'fa-warehouse');
		$this->addSidebarLink($settings, 'inventory_reason.manage', 'admin.menu.inventory_reasons', 'app_admin_inventory_reason_index', $routeParameters, 'fa-circle-question');
		$this->addSidebarLink($settings, 'exchange_rate.manage', 'admin.menu.exchange_rates', 'app_admin_exchange_rate_index', $routeParameters, 'fa-money-bill-transfer');

		if ($settings->count() > 0) {
			$menu->addChild($settings);
		}

		return $menu;
	}

	public function userAdminMenu(array $options): ItemInterface
	{
		$menu = $this->factory->createItem('userAdmin', [
			'childrenAttributes' => [
				'class' => 'sidebar-nav',
			],
		]);

		$menu->addChild($this->trans('admin.menu.profile'), [
			'route' => 'admin_user_profile',
			'routeParameters' => $this->routeParameters(),
		]);

		$menu->addChild('')
			->setLabel('<div class="dropdown-divider"></div>')
			->setExtra('safe_label', true);

		$menu->addChild($this->trans('admin.menu.logout'), [
			'route' => 'app_logout',
			'routeParameters' => $this->routeParameters(),
		]);

		foreach ($menu as $item) {
			$item->setLinkAttribute('class', 'dropdown-item');
		}

		return $menu;
	}

	/**
	 * @param array<string, mixed> $routeParameters
	 */
	private function addSidebarLink(ItemInterface $menu, string $permission, string $label, string $route, array $routeParameters, string $icon): void
	{
		if (!$this->authorizationChecker->isGranted($permission)) {
			return;
		}

		$item = $menu->addChild($this->trans($label), [
			'route' => $route,
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link',
			],
		]);
		$item->setExtra('icon', $icon);
	}

	/**
	 * @param array<string, mixed> $parameters
	 * @return array<string, mixed>
	 */
	private function routeParameters(array $parameters = []): array
	{
		$locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');

		return $locale ? array_replace($parameters, ['_locale' => $locale]) : $parameters;
	}

	private function trans(string $key): string
	{
		return $this->translator->trans($key);
	}
}

<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MenuBuilder
{
	public function __construct(
		private readonly FactoryInterface $factory,
		private readonly RequestStack $requestStack,
		private readonly TranslatorInterface $translator,
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

		$menu->addChild($this->trans('admin.menu.stores'), [
			'route' => 'admin_store_index',
			'routeParameters' => $this->routeParameters(),
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			],
		]);

		$menu->addChild($this->trans('admin.menu.managers'), [
			'route' => 'admin_user_index',
			'routeParameters' => $this->routeParameters(),
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			],
		]);

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

		$menu->addChild($this->trans('admin.menu.orders'), [
			'route' => 'app_admin_order_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$menu->addChild($this->trans('admin.menu.purchases'), [
			'route' => 'app_admin_purchase_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$menu->addChild($this->trans('admin.menu.production'), [
			'route' => 'app_admin_production_order_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$menu->addChild($this->trans('admin.menu.payments'), [
			'route' => 'app_admin_payment_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$menu->addChild($this->trans('admin.menu.inventory_documents'), [
			'route' => 'app_admin_inventory_document_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$menu->addChild($this->trans('admin.menu.customers'), [
			'route' => 'app_admin_customer_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			],
		]);

		$menu->addChild($this->trans('admin.menu.suppliers'), [
			'route' => 'app_admin_supplier_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			],
		]);

		$item = $menu->addChild($this->trans('admin.menu.settings'), [
			'uri' => '#',
			'linkAttributes' => [
				'data-bs-target' => '#setting',
				'data-bs-toggle' => 'collapse',
				'aria-expanded' => 'false',
				'class' => 'sidebar-link collapsed'
			],
			'childrenAttributes' => [
				'class' => 'sidebar-dropdown list-unstyled collapse show',
				'id' => 'setting',
				'data-bs-parent' => 'sidebar'
			],
		]);

		$item->addChild($this->trans('admin.menu.categories'), [
			'route' => 'admin_category_index',
			'routeParameters' => $routeParameters,
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

			$item->addChild($this->trans('admin.menu.products'), [
				'route' => 'admin_product_index',
				'routeParameters' => $routeParameters,
				'attributes' => [
					'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
				]
			]);

			$item->addChild($this->trans('admin.menu.units'), [
				'route' => 'app_admin_unit_index',
				'routeParameters' => $routeParameters,
				'attributes' => [
					'class' => 'sidebar-item',
				],
				'linkAttributes' => [
					'class' => 'sidebar-link'
				]
			]);

			$item->addChild($this->trans('admin.menu.warehouses'), [
				'route' => 'app_admin_warehouse_index',
				'routeParameters' => $routeParameters,
				'attributes' => [
					'class' => 'sidebar-item',
				],
				'linkAttributes' => [
					'class' => 'sidebar-link'
				]
			]);

			$item->addChild($this->trans('admin.menu.inventory_reasons'), [
				'route' => 'app_admin_inventory_reason_index',
				'routeParameters' => $routeParameters,
				'attributes' => [
					'class' => 'sidebar-item',
				],
				'linkAttributes' => [
					'class' => 'sidebar-link'
				]
			]);

			$item->addChild($this->trans('admin.menu.exchange_rates'), [
				'route' => 'app_admin_exchange_rate_index',
				'routeParameters' => $routeParameters,
				'attributes' => [
					'class' => 'sidebar-item',
				],
				'linkAttributes' => [
					'class' => 'sidebar-link'
				]
			]);

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

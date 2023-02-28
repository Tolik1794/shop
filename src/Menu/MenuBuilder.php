<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class MenuBuilder
{
	public function __construct(
		private readonly FactoryInterface $factory,
		private readonly RequestStack $requestStack,
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

		$menu->addChild('Stores', [
			'route' => 'admin_store_index',
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			],
		]);

		$menu->addChild('Managers', [
			'route' => 'admin_user_index',
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
		$storeId = $this->requestStack->getCurrentRequest()->get('store_id');

		$menu = $this->factory->createItem('mainAdminStore', [
			'childrenAttributes' => [
				'class' => 'sidebar-nav',
			],
		]);

		$menu->addChild('Orders', [
			'uri' => '#',
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$item = $menu->addChild('Setting', [
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

		$item->addChild('Categories', [
			'route' => 'admin_category_index',
			'routeParameters' => ['store_id' => $storeId],
			'attributes' => [
				'class' => 'sidebar-item',
			],
			'linkAttributes' => [
				'class' => 'sidebar-link'
			]
		]);

		$item->addChild('Products', [
			'route' => 'admin_product_index',
			'routeParameters' => ['store_id' => $storeId],
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

		$menu->addChild('Profile', [
			'route' => 'admin_user_profile'
		]);

		$menu->addChild('')
			->setLabel('<div class="dropdown-divider"></div>')
			->setExtra('safe_label', true);

		$menu->addChild('Log Out', [
			'route' => 'app_logout',
		]);

		foreach ($menu as $item) {
			$item->setLinkAttribute('class', 'dropdown-item');
		}

		return $menu;
	}
}
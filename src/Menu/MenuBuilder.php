<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;

final class MenuBuilder
{
	public function __construct(
		private FactoryInterface $factory
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
		]);

		$menu->addChild('Managers', [
			'route' => 'admin_user_index'
		]);

		foreach ($menu as $item) {
//			$item->setAttribute('class', 'dropdown-item');
//			$item->setLinkAttribute('data-bs-toggle', 'collapse');
			$item->setLinkAttribute('class', 'sidebar-link');
//			$item->setLinkAttribute('aria-expanded', 'false');
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
//			$item->setAttribute('class', 'dropdown-item');
		}

		return $menu;
	}
}
<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\Store;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

class StoreFixtures extends Fixture implements DependentFixtureInterface
{
	public const STORE_REFERENCE = 'store.furniture_works';
	public const WAREHOUSE_REFERENCE = 'warehouse.first';

	public function getDependencies(): array
	{
		return [
			CurrencyFixtures::class,
			UserFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$baseCurrency = $manager->getRepository(Currency::class)->find('UAH');

		if (!$baseCurrency instanceof Currency) {
			throw new RuntimeException('Load CurrencyFixtures before StoreFixtures.');
		}

		$store = (new Store())
			->setDescription('Furniture production and retail showroom for cabinet, dining, bedroom and office furniture.')
			->setEmail('sales@furniture-works.test')
			->setName('Furniture Works')
			->setPhone('+380671234567')
			->setSlug('furniture_works')
			->setBaseCurrency($baseCurrency)
			->setStatus(ActiveStatusEnum::ACTIVE);

		foreach ($manager->getRepository(User::class)->findAll() as $user) {
			if ($this->hasStoreAccessGroup($user)) {
				$store->addManager($user);
			}
		}

		$manager->persist($store);
		$this->addReference(self::STORE_REFERENCE, $store);

		$warehouse = (new Warehouse())
			->setStore($store)
			->setName('First Warehouse')
			->setStatus(ActiveStatusEnum::ACTIVE);

		$manager->persist($warehouse);
		$this->addReference(self::WAREHOUSE_REFERENCE, $warehouse);

		$this->createCategories($manager, $store);

		$manager->flush();
	}

	private function createCategories(ObjectManager $manager, Store $store): void
	{
		$finished = $this->category($manager, $store, 'Finished Furniture', null, 'Furniture ready for sale from showroom or made-to-order production.');
		$materials = $this->category($manager, $store, 'Production Materials', null, 'Raw materials and components used in furniture manufacturing.');

		foreach ([
			'Living Room Furniture' => 'Sofas, coffee tables and TV units for living rooms.',
			'Bedroom Furniture' => 'Beds, wardrobes and bedside furniture.',
			'Dining Room Furniture' => 'Dining tables, chairs and sideboards.',
			'Office Furniture' => 'Work desks, shelving and office storage.',
		] as $name => $description) {
			$this->category($manager, $store, $name, $finished, $description);
		}

		foreach ([
			'Wood Boards and Panels' => 'Oak, ash, plywood, MDF and chipboard boards.',
			'Furniture Hardware' => 'Hinges, runners, pulls, legs and assembly fittings.',
			'Upholstery Materials' => 'Fabric, foam and upholstery consumables.',
			'Finishing Materials' => 'Varnish, oil, glue and edge banding.',
		] as $name => $description) {
			$this->category($manager, $store, $name, $materials, $description);
		}
	}

	private function hasStoreAccessGroup(User $user): bool
	{
		foreach ($user->getGroups() as $group) {
			if (in_array($group->getCode(), ['super_admin', 'store_admin', 'manager', 'stockkeeper'], true)) {
				return true;
			}
		}

		return false;
	}

	private function category(ObjectManager $manager, Store $store, string $name, ?Category $parent, string $description): Category
	{
		$category = (new Category())
			->setName($name)
			->setDescription($description)
			->setLevel($parent ? $parent->getLevel() + 1 : 1)
			->setFirstParent($parent?->getFirstParent() ?? $parent)
			->setParent($parent)
			->setStore($store)
			->setStatus(ActiveStatusEnum::ACTIVE);

		$manager->persist($category);
		$this->addReference('category.' . $name, $category);

		return $category;
	}
}

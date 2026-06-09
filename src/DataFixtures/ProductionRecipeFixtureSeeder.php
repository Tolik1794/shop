<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductionRecipe;
use App\Entity\ProductionRecipeItem;
use App\Entity\Store;
use Doctrine\Persistence\ObjectManager;

class ProductionRecipeFixtureSeeder
{
	/**
	 * @var array<string, array<string, string>>
	 */
	private const array RECIPES = [
		'FW-SOFA-OAK-3S' => ['MAT-OAK-BRD-40' => '8.0000', 'MAT-FOAM-HD-40' => '6.0000', 'MAT-VARNISH-WB-5L' => '0.2500'],
		'FW-CT-WAL-R90' => ['MAT-OAK-BRD-40' => '4.0000', 'MAT-MDF-16' => '1.0000', 'MAT-VARNISH-WB-5L' => '0.1000'],
		'FW-TV-OAK-180' => ['MAT-PLY-BIR-18' => '3.0000', 'MAT-RUNNER-BB-450' => '3.0000', 'MAT-PULL-NI-160' => '3.0000'],
		'FW-BED-OAK-QS' => ['MAT-OAK-BRD-40' => '10.0000', 'MAT-PLY-BIR-18' => '4.0000', 'MAT-VARNISH-WB-5L' => '0.3000'],
		'FW-WARD-OAK-2D' => ['MAT-LPB-WHT-18' => '5.0000', 'MAT-HINGE-SC-110' => '6.0000', 'MAT-PULL-NI-160' => '2.0000'],
		'FW-NIGHT-WHT-1D' => ['MAT-MDF-16' => '1.0000', 'MAT-RUNNER-BB-450' => '1.0000', 'MAT-PULL-NI-160' => '1.0000'],
		'FW-DT-OAK-EXT' => ['MAT-OAK-BRD-40' => '9.0000', 'MAT-RUNNER-BB-450' => '2.0000', 'MAT-VARNISH-WB-5L' => '0.2500'],
		'FW-CHAIR-FAB-GRY' => ['MAT-ASH-BRD-30' => '3.0000', 'MAT-FOAM-HD-40' => '1.0000', 'MAT-VARNISH-WB-5L' => '0.0500'],
		'FW-DESK-ADJ-140' => ['MAT-LPB-WHT-18' => '2.0000', 'MAT-OAK-BRD-40' => '2.0000', 'MAT-VARNISH-WB-5L' => '0.1000'],
		'FW-SHELF-OAK-180' => ['MAT-PLY-BIR-18' => '4.0000', 'MAT-OAK-BRD-40' => '3.0000', 'MAT-VARNISH-WB-5L' => '0.2000'],
	];

	public function seed(ObjectManager $manager): int
	{
		$store = $manager->getRepository(Store::class)->findOneBy(['slug' => 'furniture_works']);
		if (!$store instanceof Store) {
			return 0;
		}

		$created = 0;
		foreach (self::RECIPES as $productCode => $materials) {
			$product = $this->product($manager, $store, $productCode);
			if (!$product instanceof Product || $manager->getRepository(ProductionRecipe::class)->findOneBy([
				'store' => $store,
				'product' => $product,
				'isDefault' => true,
			])) {
				continue;
			}

			$recipe = (new ProductionRecipe())
				->setStore($store)
				->setProduct($product)
				->setName('Default recipe - ' . $product->getName())
				->setIsDefault(true);

			foreach ($materials as $materialCode => $quantity) {
				$material = $this->product($manager, $store, $materialCode);
				if (!$material instanceof Product) {
					continue 2;
				}

				$recipe->addItem((new ProductionRecipeItem())
					->setMaterial($material)
					->setQuantity($quantity));
			}

			$manager->persist($recipe);
			$created++;
		}

		$manager->flush();

		return $created;
	}

	private function product(ObjectManager $manager, Store $store, string $code): ?Product
	{
		$product = $manager->getRepository(Product::class)->findOneBy([
			'store' => $store,
			'code' => $code,
		]);

		return $product instanceof Product ? $product : null;
	}
}

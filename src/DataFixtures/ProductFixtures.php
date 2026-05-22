<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductParameter;
use App\Entity\ProductParameterName;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\ActiveStatusEnum;
use App\Enum\ProductKindEnum;
use App\Enum\ProductPriceTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
	public function getDependencies(): array
	{
		return [
			StoreFixtures::class,
			UnitFixtures::class,
			ProductParameterNameFixtures::class,
			CurrencyFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$store = $this->reference(StoreFixtures::STORE_REFERENCE, Store::class);
		$warehouse = $this->reference(StoreFixtures::WAREHOUSE_REFERENCE, Warehouse::class);
		$currency = $manager->getRepository(Currency::class)->find('UAH');
		$units = $this->units($manager, $store);

		if (!$currency instanceof Currency) {
			throw new RuntimeException('Load CurrencyFixtures before ProductFixtures.');
		}

		$this->createCategoryFilters($manager);

		foreach ($this->products() as $data) {
			$unit = $units[$data['unit']] ?? null;

			if (!$unit instanceof Unit) {
				throw new RuntimeException(sprintf('Unit "%s" is missing for ProductFixtures.', $data['unit']));
			}

			$product = (new Product())
				->setStore($store)
				->setCategory($this->category($data['category']))
				->setUnit($unit)
				->setName($data['name'])
				->setCode($data['code'])
				->setCanBeSold($data['kind'] === ProductKindEnum::FINISHED_PRODUCT)
				->setCanBePurchased($data['kind'] === ProductKindEnum::MATERIAL)
				->setCanBeManufactured($data['kind'] === ProductKindEnum::FINISHED_PRODUCT)
				->setBaseSalePrice($data['kind'] === ProductKindEnum::FINISHED_PRODUCT ? $data['price'] : null)
				->setProductKind($data['kind'])
				->setStatus(ActiveStatusEnum::ACTIVE);

			foreach ($data['parameters'] as $parameterName => $value) {
				$product->addProductParameter((new ProductParameter())
					->setProductParameterName($this->parameter($parameterName))
					->setValue((string) $value));
			}

			$manager->persist($product);
			$manager->persist((new ProductPrice())
				->setStore($store)
				->setProduct($product)
				->setCurrency($currency)
				->setType(ProductPriceTypeEnum::REGULAR)
				->setPrice($data['price'])
				->setPriceBase($data['price'])
				->setValidFrom(new DateTimeImmutable('2026-01-01 00:00:00'))
				->setComment('Fixture regular catalog price.'));

			$manager->persist((new WarehouseStock())
				->setWarehouse($warehouse)
				->setProduct($product)
				->setQuantityOnHand($data['stock'])
				->setAverageCost($data['cost']));
		}

		$manager->flush();
	}

	/**
	 * @return array<string, Unit>
	 */
	private function units(ObjectManager $manager, Store $store): array
	{
		$units = [];

		foreach ($manager->getRepository(Unit::class)->findBy(['store' => $store]) as $unit) {
			$units[(string) $unit->getCode()] = $unit;
		}

		return $units;
	}

	private function createCategoryFilters(ObjectManager $manager): void
	{
		$filters = [
			'Living Room Furniture' => ['width_mm', 'height_mm', 'depth_mm', 'material', 'finish', 'color', 'style', 'weight_kg', 'seat_count'],
			'Bedroom Furniture' => ['width_mm', 'height_mm', 'depth_mm', 'material', 'finish', 'color', 'style', 'weight_kg', 'bed_size', 'storage'],
			'Dining Room Furniture' => ['width_mm', 'height_mm', 'depth_mm', 'material', 'finish', 'color', 'style', 'weight_kg', 'seat_count'],
			'Office Furniture' => ['width_mm', 'height_mm', 'depth_mm', 'material', 'finish', 'color', 'style', 'weight_kg', 'storage'],
			'Wood Boards and Panels' => ['width_mm', 'length_mm', 'thickness_mm', 'material', 'grade', 'application'],
			'Furniture Hardware' => ['length_mm', 'material', 'finish', 'color', 'application'],
			'Upholstery Materials' => ['width_mm', 'length_mm', 'thickness_mm', 'material', 'color', 'application'],
			'Finishing Materials' => ['material', 'finish', 'color', 'application'],
		];

		foreach ($filters as $categoryName => $parameterNames) {
			$category = $this->category($categoryName);

			foreach ($parameterNames as $parameterName) {
				$manager->persist((new CategoryProductParameterName())
					->setCategory($category)
					->setProductParameterName($this->parameter($parameterName))
					->setIsRequired(in_array($parameterName, ['material', 'width_mm', 'height_mm', 'depth_mm'], true))
					->setIsFilter(true));
			}
		}
	}

	/**
	 * @return array<int, array{
	 *     name: string,
	 *     code: string,
	 *     category: string,
	 *     unit: string,
	 *     kind: ProductKindEnum,
	 *     price: string,
	 *     cost: string,
	 *     stock: string,
	 *     parameters: array<string, string|int>
	 * }>
	 */
	private function products(): array
	{
		return [
			[
				'name' => 'Oak Frame Sofa 3 Seat',
				'code' => 'FW-SOFA-OAK-3S',
				'category' => 'Living Room Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '58900.0000',
				'cost' => '34200.0000',
				'stock' => '3.0000',
				'parameters' => ['width_mm' => 2180, 'height_mm' => 840, 'depth_mm' => 920, 'material' => 'oak frame, fabric upholstery', 'finish' => 'matte oil', 'color' => 'warm grey', 'style' => 'modern', 'weight_kg' => 74, 'seat_count' => 3],
			],
			[
				'name' => 'Walnut Coffee Table Round',
				'code' => 'FW-CT-WAL-R90',
				'category' => 'Living Room Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '18400.0000',
				'cost' => '9700.0000',
				'stock' => '5.0000',
				'parameters' => ['width_mm' => 900, 'height_mm' => 420, 'depth_mm' => 900, 'material' => 'walnut veneer, MDF core', 'finish' => 'satin lacquer', 'color' => 'dark walnut', 'style' => 'mid-century', 'weight_kg' => 28],
			],
			[
				'name' => 'Modular TV Stand White Oak',
				'code' => 'FW-TV-OAK-180',
				'category' => 'Living Room Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '24900.0000',
				'cost' => '13800.0000',
				'stock' => '4.0000',
				'parameters' => ['width_mm' => 1800, 'height_mm' => 520, 'depth_mm' => 420, 'material' => 'oak veneer, birch plywood', 'finish' => 'clear lacquer', 'color' => 'natural oak', 'style' => 'scandinavian', 'weight_kg' => 46, 'storage' => '3 drawers, 2 open shelves'],
			],
			[
				'name' => 'Queen Storage Bed Oak',
				'code' => 'FW-BED-OAK-QS',
				'category' => 'Bedroom Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '43500.0000',
				'cost' => '25500.0000',
				'stock' => '2.0000',
				'parameters' => ['width_mm' => 1660, 'height_mm' => 1050, 'depth_mm' => 2140, 'material' => 'solid oak, plywood slats', 'finish' => 'hardwax oil', 'color' => 'natural oak', 'style' => 'contemporary', 'weight_kg' => 92, 'bed_size' => '1600x2000', 'storage' => '2 lift-up compartments'],
			],
			[
				'name' => 'Two Door Oak Wardrobe',
				'code' => 'FW-WARD-OAK-2D',
				'category' => 'Bedroom Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '52800.0000',
				'cost' => '30900.0000',
				'stock' => '2.0000',
				'parameters' => ['width_mm' => 1200, 'height_mm' => 2200, 'depth_mm' => 620, 'material' => 'oak veneer, laminated board', 'finish' => 'matte lacquer', 'color' => 'natural oak', 'style' => 'minimalist', 'weight_kg' => 118, 'storage' => 'hanging rail, 4 shelves'],
			],
			[
				'name' => 'Floating Bedside Table',
				'code' => 'FW-NIGHT-WHT-1D',
				'category' => 'Bedroom Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '6900.0000',
				'cost' => '3300.0000',
				'stock' => '8.0000',
				'parameters' => ['width_mm' => 450, 'height_mm' => 180, 'depth_mm' => 350, 'material' => 'painted MDF', 'finish' => 'matte paint', 'color' => 'white', 'style' => 'minimalist', 'weight_kg' => 8, 'storage' => '1 drawer'],
			],
			[
				'name' => 'Extendable Dining Table Oak',
				'code' => 'FW-DT-OAK-EXT',
				'category' => 'Dining Room Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '49600.0000',
				'cost' => '28600.0000',
				'stock' => '3.0000',
				'parameters' => ['width_mm' => 1800, 'height_mm' => 760, 'depth_mm' => 900, 'material' => 'solid oak, steel runner', 'finish' => 'hardwax oil', 'color' => 'natural oak', 'style' => 'modern farmhouse', 'weight_kg' => 86, 'seat_count' => 8],
			],
			[
				'name' => 'Upholstered Dining Chair',
				'code' => 'FW-CHAIR-FAB-GRY',
				'category' => 'Dining Room Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '7400.0000',
				'cost' => '3900.0000',
				'stock' => '18.0000',
				'parameters' => ['width_mm' => 480, 'height_mm' => 850, 'depth_mm' => 560, 'material' => 'beech frame, fabric upholstery', 'finish' => 'stained lacquer', 'color' => 'charcoal', 'style' => 'modern', 'weight_kg' => 7, 'seat_count' => 1],
			],
			[
				'name' => 'Standing Office Desk',
				'code' => 'FW-DESK-ADJ-140',
				'category' => 'Office Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '31800.0000',
				'cost' => '18900.0000',
				'stock' => '4.0000',
				'parameters' => ['width_mm' => 1400, 'height_mm' => 1250, 'depth_mm' => 700, 'material' => 'laminated board, steel frame', 'finish' => 'powder coated frame', 'color' => 'white oak', 'style' => 'office modern', 'weight_kg' => 54, 'storage' => 'cable tray'],
			],
			[
				'name' => 'Open Oak Bookshelf',
				'code' => 'FW-SHELF-OAK-180',
				'category' => 'Office Furniture',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::FINISHED_PRODUCT,
				'price' => '22600.0000',
				'cost' => '12700.0000',
				'stock' => '5.0000',
				'parameters' => ['width_mm' => 900, 'height_mm' => 1800, 'depth_mm' => 320, 'material' => 'oak veneer, plywood', 'finish' => 'clear lacquer', 'color' => 'natural oak', 'style' => 'scandinavian', 'weight_kg' => 42, 'storage' => '5 open shelves'],
			],
			[
				'name' => 'Solid Oak Board 40mm',
				'code' => 'MAT-OAK-BRD-40',
				'category' => 'Wood Boards and Panels',
				'unit' => 'm',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '1480.0000',
				'cost' => '1480.0000',
				'stock' => '120.0000',
				'parameters' => ['width_mm' => 220, 'length_mm' => 3000, 'thickness_mm' => 40, 'material' => 'solid oak', 'grade' => 'A/B', 'application' => 'tabletops, bed frames, visible rails'],
			],
			[
				'name' => 'Solid Ash Board 30mm',
				'code' => 'MAT-ASH-BRD-30',
				'category' => 'Wood Boards and Panels',
				'unit' => 'm',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '1120.0000',
				'cost' => '1120.0000',
				'stock' => '90.0000',
				'parameters' => ['width_mm' => 200, 'length_mm' => 3000, 'thickness_mm' => 30, 'material' => 'solid ash', 'grade' => 'A/B', 'application' => 'chair frames, legs, edge details'],
			],
			[
				'name' => 'Birch Plywood Sheet 18mm',
				'code' => 'MAT-PLY-BIR-18',
				'category' => 'Wood Boards and Panels',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '2450.0000',
				'cost' => '2450.0000',
				'stock' => '45.0000',
				'parameters' => ['width_mm' => 1250, 'length_mm' => 2500, 'thickness_mm' => 18, 'material' => 'birch plywood', 'grade' => 'BB/BB', 'application' => 'drawer boxes, bed bases, cabinet structure'],
			],
			[
				'name' => 'MDF Panel 16mm',
				'code' => 'MAT-MDF-16',
				'category' => 'Wood Boards and Panels',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '1390.0000',
				'cost' => '1390.0000',
				'stock' => '60.0000',
				'parameters' => ['width_mm' => 1220, 'length_mm' => 2800, 'thickness_mm' => 16, 'material' => 'MDF', 'grade' => 'E1', 'application' => 'painted fronts, shelves, panels'],
			],
			[
				'name' => 'White Laminated Chipboard 18mm',
				'code' => 'MAT-LPB-WHT-18',
				'category' => 'Wood Boards and Panels',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '1180.0000',
				'cost' => '1180.0000',
				'stock' => '75.0000',
				'parameters' => ['width_mm' => 1830, 'length_mm' => 2750, 'thickness_mm' => 18, 'material' => 'laminated chipboard', 'grade' => 'E1', 'application' => 'wardrobe carcasses, shelves, desk tops', 'color' => 'white'],
			],
			[
				'name' => 'Soft Close Cabinet Hinge',
				'code' => 'MAT-HINGE-SC-110',
				'category' => 'Furniture Hardware',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '185.0000',
				'cost' => '185.0000',
				'stock' => '300.0000',
				'parameters' => ['length_mm' => 110, 'material' => 'nickel plated steel', 'finish' => 'nickel plated', 'color' => 'silver', 'application' => 'cabinet doors'],
			],
			[
				'name' => 'Ball Bearing Drawer Runner 450mm',
				'code' => 'MAT-RUNNER-BB-450',
				'category' => 'Furniture Hardware',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '320.0000',
				'cost' => '320.0000',
				'stock' => '180.0000',
				'parameters' => ['length_mm' => 450, 'material' => 'zinc plated steel', 'finish' => 'zinc plated', 'color' => 'silver', 'application' => 'drawers'],
			],
			[
				'name' => 'Brushed Nickel Furniture Pull',
				'code' => 'MAT-PULL-NI-160',
				'category' => 'Furniture Hardware',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '145.0000',
				'cost' => '145.0000',
				'stock' => '240.0000',
				'parameters' => ['length_mm' => 160, 'material' => 'zinc alloy', 'finish' => 'brushed nickel', 'color' => 'nickel', 'application' => 'drawers and doors'],
			],
			[
				'name' => 'High Density Upholstery Foam 40mm',
				'code' => 'MAT-FOAM-HD-40',
				'category' => 'Upholstery Materials',
				'unit' => 'm',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '690.0000',
				'cost' => '690.0000',
				'stock' => '85.0000',
				'parameters' => ['width_mm' => 1600, 'length_mm' => 1000, 'thickness_mm' => 40, 'material' => 'high density polyurethane foam', 'color' => 'white', 'application' => 'chair and sofa cushions'],
			],
			[
				'name' => 'Water-Based Clear Varnish 5L',
				'code' => 'MAT-VARNISH-WB-5L',
				'category' => 'Finishing Materials',
				'unit' => 'pcs',
				'kind' => ProductKindEnum::MATERIAL,
				'price' => '1680.0000',
				'cost' => '1680.0000',
				'stock' => '32.0000',
				'parameters' => ['material' => 'water-based polyurethane varnish', 'finish' => 'clear satin', 'color' => 'clear', 'application' => 'wooden furniture finishing'],
			],
		];
	}

	private function category(string $name): Category
	{
		return $this->reference('category.' . $name, Category::class);
	}

	private function parameter(string $name): ProductParameterName
	{
		return $this->reference('parameter.' . $name, ProductParameterName::class);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return T
	 */
	private function reference(string $name, string $className): object
	{
		$reference = $this->getReference($name, $className);

		if (!$reference instanceof $className) {
			throw new RuntimeException(sprintf('Fixture reference "%s" is not a %s.', $name, $className));
		}

		return $reference;
	}
}

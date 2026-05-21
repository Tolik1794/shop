<?php

namespace App\Tests\Service\Production;

use App\Entity\Product;
use App\Entity\ProductionRecipe;
use App\Entity\ProductionRecipeItem;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use App\Service\Production\ProductionOrderFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductionOrderFactoryTest extends TestCase
{
	private ProductionOrderFactory $factory;

	protected function setUp(): void
	{
		$this->factory = new ProductionOrderFactory();
	}

	public function testCreateFromRecipeCopiesMaterialSnapshotWithWaste(): void
	{
		$store = new Store();
		$output = $this->product($store, ProductKindEnum::FINISHED_PRODUCT, true);
		$material = $this->product($store, ProductKindEnum::MATERIAL);
		$warehouse = (new Warehouse())->setStore($store);
		$recipe = (new ProductionRecipe())
			->setStore($store)
			->setProduct($output)
			->setName('Default recipe')
			->addItem((new ProductionRecipeItem())
				->setMaterial($material)
				->setQuantity('2.5000')
				->setWastePercent('10.00'));

		$order = $this->factory->createFromRecipe($recipe, '4.0000', $warehouse);
		$orderMaterial = $order->getMaterials()->first();

		self::assertSame($store, $order->getStore());
		self::assertSame($output, $order->getProduct());
		self::assertSame($recipe, $order->getRecipe());
		self::assertSame($warehouse, $order->getWarehouse());
		self::assertSame('4.0000', $order->getPlannedQuantity());
		self::assertSame($material, $orderMaterial->getMaterial());
		self::assertSame('11.0000', $orderMaterial->getPlannedQuantity());
		self::assertSame('10.00', $orderMaterial->getWastePercent());
		self::assertSame($recipe->getItems()->first(), $orderMaterial->getRecipeItem());
	}

	public function testCreateProductionDocumentUsesOutputInAndMaterialOutLines(): void
	{
		$store = new Store();
		$output = $this->product($store, ProductKindEnum::FINISHED_PRODUCT, true);
		$material = $this->product($store, ProductKindEnum::MATERIAL);
		$warehouse = (new Warehouse())->setStore($store);
		$recipe = (new ProductionRecipe())
			->setStore($store)
			->setProduct($output)
			->setName('Default recipe')
			->addItem((new ProductionRecipeItem())
				->setMaterial($material)
				->setQuantity('2.0000'));
		$order = $this->factory->createFromRecipe($recipe, '5.0000', $warehouse);

		$document = $this->factory->createProductionDocument($order, '2.0000');
		$lines = $document->getLines()->toArray();

		self::assertSame(InventoryDocumentType::PRODUCTION, $document->getType());
		self::assertSame($order, $document->getProductionOrder());
		self::assertSame(InventoryDirection::OUT, $lines[0]->getDirection());
		self::assertSame($material, $lines[0]->getProduct());
		self::assertSame('4.0000', $lines[0]->getQuantity());
		self::assertSame(InventoryDirection::IN, $lines[1]->getDirection());
		self::assertSame($output, $lines[1]->getProduct());
		self::assertSame('2.0000', $lines[1]->getQuantity());
	}

	public function testServiceProductCannotBeProductionMaterial(): void
	{
		$store = new Store();
		$output = $this->product($store, ProductKindEnum::FINISHED_PRODUCT, true);
		$service = $this->product($store, ProductKindEnum::SERVICE);
		$recipe = (new ProductionRecipe())
			->setStore($store)
			->setProduct($output)
			->setName('Invalid recipe')
			->addItem((new ProductionRecipeItem())
				->setMaterial($service)
				->setQuantity('1.0000'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Service product cannot be production material.');

		$this->factory->createFromRecipe($recipe, '1.0000');
	}

	private function product(Store $store, ProductKindEnum $kind, bool $canBeManufactured = false): Product
	{
		return (new Product())
			->setStore($store)
			->setName($kind->value . '-' . uniqid())
			->setCode($kind->value . '-' . uniqid())
			->setProductKind($kind)
			->setCanBeSold(false)
			->setCanBePurchased(true)
			->setCanBeManufactured($canBeManufactured);
	}
}

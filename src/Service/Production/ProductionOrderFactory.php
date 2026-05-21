<?php

namespace App\Service\Production;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Product;
use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderMaterial;
use App\Entity\ProductionRecipe;
use App\Entity\ProductionRecipeItem;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use DateTimeImmutable;
use RuntimeException;

class ProductionOrderFactory
{
	public function createFromRecipe(
		ProductionRecipe $recipe,
		string $plannedQuantity,
		?Warehouse $warehouse = null,
		?User $actor = null,
	): ProductionOrder
	{
		$this->assertPositive($plannedQuantity, 'Planned production quantity must be greater than zero.');
		$this->assertRecipeCanBeUsed($recipe);

		if ($warehouse instanceof Warehouse && $warehouse->getStore()?->getId() !== $recipe->getStore()?->getId()) {
			throw new RuntimeException('Production warehouse must belong to recipe store.');
		}

		$order = (new ProductionOrder())
			->setStore($recipe->getStore())
			->setProduct($recipe->getProduct())
			->setRecipe($recipe)
			->setWarehouse($warehouse)
			->setPlannedQuantity($this->formatQuantity($plannedQuantity))
			->setCreatedBy($actor)
			->setUpdatedBy($actor);

		foreach ($recipe->getItems() as $recipeItem) {
			$order->addMaterial($this->createMaterialSnapshot($recipeItem, $plannedQuantity, $recipe));
		}

		return $order;
	}

	public function createProductionDocument(
		ProductionOrder $order,
		string $completedQuantity,
		?User $actor = null,
	): InventoryDocument
	{
		$this->assertPositive($completedQuantity, 'Completed production quantity must be greater than zero.');

		$store = $order->getStore();
		$product = $order->getProduct();
		$warehouse = $order->getWarehouse();

		if (!$store || !$product || !$warehouse) {
			throw new RuntimeException('Production order must have store, product and warehouse.');
		}

		$document = (new InventoryDocument())
			->setStore($store)
			->setNumber($this->productionDocumentNumber($order))
			->setType(InventoryDocumentType::PRODUCTION)
			->setDocumentDate(new DateTimeImmutable())
			->setComment(sprintf('Production order #%s', $order->getId() ?? 'new'))
			->setProductionOrder($order)
			->setCreatedBy($actor)
			->setUpdatedBy($actor);

		foreach ($order->getMaterials() as $material) {
			$document->addLine((new InventoryDocumentLine())
				->setProduct($material->getMaterial())
				->setWarehouse($warehouse)
				->setDirection(InventoryDirection::OUT)
				->setQuantity($this->quantityForCompletedOutput($material->getPlannedQuantity(), $order->getPlannedQuantity(), $completedQuantity)));
		}

		$document->addLine((new InventoryDocumentLine())
			->setProduct($product)
			->setWarehouse($warehouse)
			->setDirection(InventoryDirection::IN)
			->setQuantity($this->formatQuantity($completedQuantity)));

		return $document;
	}

	private function createMaterialSnapshot(ProductionRecipeItem $recipeItem, string $plannedQuantity, ProductionRecipe $recipe): ProductionOrderMaterial
	{
		$material = $recipeItem->getMaterial();
		$this->assertMaterialCanBeUsed($material);

		if ($material?->getStore()?->getId() !== $recipe->getStore()?->getId()) {
			throw new RuntimeException('Production recipe material must belong to recipe store.');
		}

		$baseQuantity = $this->numberValue($recipeItem->getQuantity()) * $this->numberValue($plannedQuantity);
		$wasteMultiplier = 1 + ($this->numberValue($recipeItem->getWastePercent()) / 100);

		return (new ProductionOrderMaterial())
			->setMaterial($material)
			->setRecipeItem($recipeItem)
			->setWastePercent($recipeItem->getWastePercent())
			->setPlannedQuantity($this->formatQuantity($baseQuantity * $wasteMultiplier));
	}

	private function assertRecipeCanBeUsed(ProductionRecipe $recipe): void
	{
		$product = $recipe->getProduct();
		$store = $recipe->getStore();

		if (!$store || !$product) {
			throw new RuntimeException('Production recipe must have store and product.');
		}

		if ($recipe->getStatus() !== ActiveStatusEnum::ACTIVE) {
			throw new RuntimeException('Inactive production recipe cannot be used.');
		}

		if (!$product->isCanBeManufactured()) {
			throw new RuntimeException('Production recipe product must be manufacturable.');
		}

		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			throw new RuntimeException('Service product cannot be production output.');
		}

		if ($product->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Production recipe product must belong to recipe store.');
		}

		if ($recipe->getItems()->isEmpty()) {
			throw new RuntimeException('Production recipe must contain at least one material.');
		}
	}

	private function assertMaterialCanBeUsed(?Product $material): void
	{
		if (!$material) {
			throw new RuntimeException('Production recipe material is required.');
		}

		if ($material->getProductKind() === ProductKindEnum::SERVICE) {
			throw new RuntimeException('Service product cannot be production material.');
		}
	}

	private function quantityForCompletedOutput(?string $plannedMaterialQuantity, ?string $plannedOutputQuantity, string $completedQuantity): string
	{
		$plannedOutput = $this->numberValue($plannedOutputQuantity);
		if ($plannedOutput <= 0) {
			throw new RuntimeException('Production order planned quantity must be greater than zero.');
		}

		return $this->formatQuantity($this->numberValue($plannedMaterialQuantity) * ($this->numberValue($completedQuantity) / $plannedOutput));
	}

	private function assertPositive(string $quantity, string $message): void
	{
		if ($this->numberValue($quantity) <= 0) {
			throw new RuntimeException($message);
		}
	}

	private function productionDocumentNumber(ProductionOrder $order): string
	{
		return mb_substr(sprintf('PRD-%s-%s', $order->getId() ?? 'new', (new DateTimeImmutable())->format('YmdHis')), 0, 255);
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatQuantity(float|string $value): string
	{
		return number_format((float) $value, 4, '.', '');
	}
}

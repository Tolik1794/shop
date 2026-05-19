<?php

namespace App\Dto\Api\Admin\Order;

use App\Entity\Product;
use App\Entity\Store;
use JsonSerializable;

class ProductSearchProductDto implements JsonSerializable
{
	/**
	 * @param ProductSearchStockOptionDto[] $stockOptions
	 */
	public function __construct(
		private readonly ?int $id,
		private readonly string $text,
		private readonly ?string $name,
		private readonly ?string $code,
		private readonly ?string $unit,
		private readonly int $unitPrecision,
		private readonly ?string $price,
		private readonly string $available,
		private readonly array $stockOptions,
		private readonly ?ProductSearchProductionOptionDto $productionOption,
	)
	{
	}

	/**
	 * @param string[] $excludedOptionKeys
	 */
	public static function fromProduct(Product $product, Store $store, ?string $price, array $excludedOptionKeys = []): ?self
	{
		$availableQuantity = 0.0;
		$stockOptions = [];
		$allStockOptionCount = 0;

		foreach ($product->getWarehouseStocks() as $warehouseStock) {
			if ($warehouseStock->getWarehouse()?->getStore()?->getId() !== $store->getId()) {
				continue;
			}

			$allStockOptionCount++;
			if (in_array(self::stockOptionKey($product, $warehouseStock->getWarehouse()?->getId()), $excludedOptionKeys, true)) {
				continue;
			}

			$available = max(0, (float) $warehouseStock->getQuantityOnHand() - (float) $warehouseStock->getReservedQuantity());
			$availableQuantity += $available;
			$stockOptions[] = new ProductSearchStockOptionDto(
				warehouseId: $warehouseStock->getWarehouse()?->getId(),
				warehouseName: $warehouseStock->getWarehouse()?->getName(),
				available: self::formatQuantity($available),
				price: $price,
			);
		}

		$productionOption = $product->isCanBeManufactured()
			&& !in_array(self::productionOptionKey($product), $excludedOptionKeys, true)
				? new ProductSearchProductionOptionDto($price)
				: null;
		$hasFallbackOption = $allStockOptionCount === 0 && !$product->isCanBeManufactured();

		if ($hasFallbackOption && in_array(self::fallbackOptionKey($product), $excludedOptionKeys, true)) {
			return null;
		}

		if (!$hasFallbackOption && count($stockOptions) === 0 && $productionOption === null) {
			return null;
		}

		return new self(
			id: $product->getId(),
			text: sprintf('%s (%s)', $product->getName(), $product->getCode()),
			name: $product->getName(),
			code: $product->getCode(),
			unit: $product->getUnit()?->getCode(),
			unitPrecision: max(0, (int) ($product->getUnit()?->getPrecision() ?? 4)),
			price: $price,
			available: self::formatQuantity(max(0, $availableQuantity)),
			stockOptions: $stockOptions,
			productionOption: $productionOption,
		);
	}

	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'text' => $this->text,
			'name' => $this->name,
			'code' => $this->code,
			'unit' => $this->unit,
			'unitPrecision' => $this->unitPrecision,
			'price' => $this->price,
			'available' => $this->available,
			'stockOptions' => $this->stockOptions,
			'productionOption' => $this->productionOption,
		];
	}

	private static function formatQuantity(float $value): string
	{
		return number_format($value, 4, '.', '');
	}

	private static function stockOptionKey(Product $product, ?int $warehouseId): string
	{
		return sprintf('stock:%d:%s', $product->getId(), $warehouseId ?? '');
	}

	private static function productionOptionKey(Product $product): string
	{
		return sprintf('production:%d', $product->getId());
	}

	private static function fallbackOptionKey(Product $product): string
	{
		return sprintf('stock:%d:', $product->getId());
	}
}

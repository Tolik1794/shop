<?php

namespace App\Dto\Api\Admin\Order;

use App\Entity\Currency;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Service\Order\OrderBatchPricingService;
use App\Service\Discount\ProductDiscountResolver;
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
		private readonly array $discountRules = [],
		private readonly ?int $defaultDiscountRuleId = null,
	)
	{
	}

	/**
	 * @param string[] $excludedOptionKeys
	 */
	public static function fromProduct(
		Product $product,
		Store $store,
		?string $price,
		array $excludedOptionKeys = [],
		?OrderBatchPricingService $orderBatchPricingService = null,
		?Currency $currency = null,
		?ProductDiscountResolver $productDiscountResolver = null,
	): ?self
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

			$warehouse = $warehouseStock->getWarehouse();
			$batchLayers = [];
			if ($orderBatchPricingService instanceof OrderBatchPricingService && $warehouse instanceof Warehouse && $currency instanceof Currency) {
				$batchLayers = array_map(
					static fn($layer): ProductSearchBatchLayerDto => ProductSearchBatchLayerDto::fromLayer($layer),
					$orderBatchPricingService->findLayers($product, $warehouse, $store, $currency),
				);
			}

			if ($batchLayers !== []) {
				foreach ($batchLayers as $batchLayer) {
					if (in_array(self::stockOptionKey($product, $warehouse?->getId(), $batchLayer->getBatchId()), $excludedOptionKeys, true)) {
						continue;
					}

					$available = (float) $batchLayer->getAvailable();
					$availableQuantity += $available;
					$stockOptions[] = new ProductSearchStockOptionDto(
						warehouseId: $warehouse?->getId(),
						warehouseName: $warehouse?->getName(),
						available: self::formatQuantity($available),
						price: $batchLayer->getPrice() ?? $price,
						batchId: $batchLayer->getBatchId(),
						batchReceivedAt: $batchLayer->getReceivedAt(),
						priceSource: $batchLayer->getPriceSource(),
						batchLayers: [$batchLayer],
					);
				}

				continue;
			}

			if (in_array(self::stockOptionKey($product, $warehouse?->getId()), $excludedOptionKeys, true)) {
				continue;
			}

			$available = max(0, (float) $warehouseStock->getQuantityOnHand() - (float) $warehouseStock->getReservedQuantity());
			$availableQuantity += $available;
			$stockOptions[] = new ProductSearchStockOptionDto(
				warehouseId: $warehouse?->getId(),
				warehouseName: $warehouse?->getName(),
				available: self::formatQuantity($available),
				price: $price,
			);
		}

		$productionOption = $product->isCanBeManufactured()
			&& !in_array(self::productionOptionKey($product), $excludedOptionKeys, true)
				? new ProductSearchProductionOptionDto($price)
				: null;
		$discountRules = $productDiscountResolver instanceof ProductDiscountResolver
			? array_map(
				static fn (ProductDiscountRule $rule): array => [
					'id' => $rule->getId(),
					'name' => $rule->getName(),
					'percent' => $rule->getPercent(),
					'isDefault' => $rule->isDefault(),
				],
				$productDiscountResolver->allowedRules($product, $store),
			)
			: [];
		$defaultDiscountRule = $productDiscountResolver instanceof ProductDiscountResolver
			? $productDiscountResolver->defaultRule($product, $store)
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
			discountRules: $discountRules,
			defaultDiscountRuleId: $defaultDiscountRule?->getId(),
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
			'discountRules' => $this->discountRules,
			'defaultDiscountRuleId' => $this->defaultDiscountRuleId,
		];
	}

	private static function formatQuantity(float $value): string
	{
		return number_format($value, 4, '.', '');
	}

	private static function stockOptionKey(Product $product, ?int $warehouseId, ?int $batchId = null): string
	{
		$key = sprintf('stock:%d:%s', $product->getId(), $warehouseId ?? '');

		return $batchId !== null ? sprintf('%s:%d', $key, $batchId) : $key;
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

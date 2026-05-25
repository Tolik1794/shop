<?php

namespace App\Service\Order;

use App\Entity\Currency;
use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Entity\WarehouseStockBatch;
use App\Repository\StockReservationRepository;
use App\Repository\WarehouseStockBatchRepository;
use App\Repository\WarehouseStockRepository;
use App\Service\ExchangeRateResolver;
use App\Service\Pricing\CatalogPriceResolver;
use RuntimeException;

class OrderBatchPricingService
{
	public const SOURCE_BATCH_SALE_PRICE = 'batch_sale_price';
	public const SOURCE_CATALOG_FALLBACK = 'catalog_fallback';

	public function __construct(
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly WarehouseStockBatchRepository $warehouseStockBatchRepository,
		private readonly StockReservationRepository $stockReservationRepository,
		private readonly CatalogPriceResolver $catalogPriceResolver,
		private readonly ExchangeRateResolver $exchangeRateResolver,
	)
	{
	}

	/**
	 * @return OrderBatchPriceLayer[]
	 */
	public function findLayers(Product $product, Warehouse $warehouse, Store $store, Currency $currency, ?string $quantity = null): array
	{
		$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);

		if ($warehouseStock === null) {
			return [];
		}

		$remaining = $quantity !== null ? $this->numberValue($quantity) : null;
		$layers = [];

		foreach ($this->warehouseStockBatchRepository->findOpenByWarehouseStock($warehouseStock) as $batch) {
			$available = $this->availableBatchQuantity($batch);

			if ($available <= 0.00005) {
				continue;
			}

			if ($remaining !== null) {
				if ($remaining <= 0.00005) {
					break;
				}

				$available = min($available, $remaining);
				$remaining -= $available;
			}

			$layers[] = new OrderBatchPriceLayer(
				batch: $batch,
				available: $this->formatQuantity($available),
				price: $this->resolveBatchPrice($batch, $product, $store, $currency),
				priceSource: $batch->getSalePrice() !== null ? self::SOURCE_BATCH_SALE_PRICE : self::SOURCE_CATALOG_FALLBACK,
			);
		}

		return $layers;
	}

	public function applyBatchPrice(OrderEntry $orderEntry): void
	{
		$batch = $orderEntry->getWarehouseStockBatch();
		$order = $orderEntry->getOrder();
		$product = $orderEntry->getProduct();
		$store = $order?->getStore();
		$currency = $order?->getCurrency();

		if (
			!$batch instanceof WarehouseStockBatch
			|| !$product instanceof Product
			|| !$store instanceof Store
			|| !$currency instanceof Currency
		) {
			return;
		}

		$price = $this->resolveBatchPrice($batch, $product, $store, $currency);

		if ($price !== null && (float) $orderEntry->getUnitPrice() <= 0) {
			$orderEntry->setUnitPrice($price);
		}
	}

	public function normalizeOrderEntries(Order $order): void
	{
		foreach ($order->getOrderEntries()->toArray() as $orderEntry) {
			if (!$orderEntry instanceof OrderEntry) {
				continue;
			}

			$this->assertBatchMatchesOrderEntry($orderEntry);
			$this->applyBatchPrice($orderEntry);
		}
	}

	public function assertBatchMatchesOrderEntry(OrderEntry $orderEntry): void
	{
		$batch = $orderEntry->getWarehouseStockBatch();

		if (!$batch instanceof WarehouseStockBatch) {
			return;
		}

		$warehouseStock = $batch->getWarehouseStock();

		if (
			$warehouseStock === null
			|| $warehouseStock->getProduct() !== $orderEntry->getProduct()
			|| $warehouseStock->getWarehouse() !== $orderEntry->getWarehouse()
		) {
			throw new RuntimeException('Selected stock batch does not match order entry product and warehouse.');
		}

		$alreadyReserved = $this->numberValue($this->stockReservationRepository->getActiveQuantityForOrderEntry($orderEntry));
		if ($this->numberValue($orderEntry->getQuantity()) > $this->availableBatchQuantity($batch) + $alreadyReserved + 0.00005) {
			throw new RuntimeException('Selected stock batch does not have enough available quantity.');
		}
	}

	private function resolveBatchPrice(WarehouseStockBatch $batch, Product $product, Store $store, Currency $currency): ?string
	{
		if ($batch->getSalePrice() !== null) {
			return $this->convertBasePrice((float) $batch->getSalePrice(), $store, $currency);
		}

		return $this->catalogPriceResolver->tryResolve($product, $store, $currency)?->getAmount();
	}

	private function convertBasePrice(float $basePrice, Store $store, Currency $currency): string
	{
		$baseCurrency = $store->getBaseCurrency();

		if (!$baseCurrency instanceof Currency || $baseCurrency->getCode() === $currency->getCode()) {
			return $this->formatMoney($basePrice);
		}

		$exchangeRateToBase = (float) $this->exchangeRateResolver->resolve($currency, $baseCurrency, $store);
		$amount = $exchangeRateToBase > 0 ? $basePrice / $exchangeRateToBase : $basePrice;

		return $this->formatMoney($amount);
	}

	private function availableBatchQuantity(WarehouseStockBatch $batch): float
	{
		return max(
			0.0,
			$this->numberValue($batch->getRemainingQuantity()) - $this->numberValue($this->stockReservationRepository->getActiveQuantityForBatch($batch)),
		);
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatQuantity(float $value): string
	{
		return number_format($value, 4, '.', '');
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

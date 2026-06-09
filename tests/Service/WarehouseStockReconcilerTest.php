<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Entity\WarehouseStockBatch;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use App\Service\WarehouseStockReconciler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WarehouseStockReconcilerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private WarehouseStockReconciler $reconciler;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->reconciler = static::getContainer()->get(WarehouseStockReconciler::class);
	}

	public function testReconcileRepairsBalancesAndFallsBackFromOverdrawnAssignedBatch(): void
	{
		[$stock, $line] = $this->createStockAndLine('4.0000');
		$firstBatch = $this->batch($stock, '1.0000', '1.0000', '2026-01-01');
		$secondBatch = $this->batch($stock, '2.0000', '2.0000', '2026-01-02');
		$excessBatch = $this->batch($stock, '1.0000', '1.0000', '2026-01-03');
		$firstMovement = $this->movement($stock, $line, $firstBatch, '-2.0000', '1.0000', '2026-02-01');
		$secondMovement = $this->movement($stock, $line, $secondBatch, '-1.0000', '1.0000', '2026-02-02');
		$this->entityManager->flush();

		$dryRun = $this->reconciler->reconcile(stockId: $stock->getId());
		self::assertCount(1, $dryRun['stocks']);
		self::assertSame('0.0000', $dryRun['stocks'][0]['expected']);
		self::assertSame(3, $dryRun['stocks'][0]['batch_changes']);
		self::assertSame(1, $dryRun['stocks'][0]['movement_changes']);

		$this->reconciler->reconcile(stockId: $stock->getId(), apply: true);
		$this->entityManager->refresh($stock);
		$this->entityManager->refresh($firstBatch);
		$this->entityManager->refresh($secondBatch);
		$this->entityManager->refresh($excessBatch);
		$this->entityManager->refresh($firstMovement);
		$this->entityManager->refresh($secondMovement);

		self::assertSame('0.0000', $stock->getQuantityOnHand());
		self::assertSame('0.0000', $firstBatch->getRemainingQuantity());
		self::assertSame('0.0000', $secondBatch->getRemainingQuantity());
		self::assertSame('0.0000', $excessBatch->getRemainingQuantity());
		self::assertSame('1.0000', $firstMovement->getBalanceAfter());
		self::assertSame('0.0000', $secondMovement->getBalanceAfter());
	}

	public function testReconcileCreatesMissingOpeningLayer(): void
	{
		[$stock, $line] = $this->createStockAndLine('4.0000');
		$batch = $this->batch($stock, '1.0000', '1.0000', '2026-01-01');
		$movement = $this->movement($stock, $line, $batch, '-1.0000', '2.0000', '2026-02-01');
		$this->entityManager->flush();

		$dryRun = $this->reconciler->reconcile(stockId: $stock->getId());
		self::assertTrue($dryRun['stocks'][0]['opening_batch_created']);

		$this->reconciler->reconcile(stockId: $stock->getId(), apply: true);
		$this->entityManager->clear();
		$stock = $this->entityManager->find(WarehouseStock::class, $stock->getId());

		self::assertSame('2.0000', $stock?->getQuantityOnHand());
		self::assertCount(2, $stock?->getWarehouseStockBatches() ?? []);
		self::assertSame(2.0, array_reduce(
			$stock?->getWarehouseStockBatches()->toArray() ?? [],
			static fn (float $sum, WarehouseStockBatch $item): float => $sum + (float) $item->getRemainingQuantity(),
			0.0,
		));
	}

	/**
	 * @return array{WarehouseStock, InventoryDocumentLine}
	 */
	private function createStockAndLine(string $storedQuantity): array
	{
		$suffix = substr(uniqid(), -8);
		$currency = (new Currency())->setCode($this->uniqueCurrencyCode())->setName('Currency')->setSymbol('C')->setDecimalPlaces(2);
		$store = (new Store())->setName('Store ' . $suffix)->setSlug('store-' . $suffix)->setPhone('+380000000000')->setEmail($suffix . '@example.com')->setBaseCurrency($currency);
		$warehouse = (new Warehouse())->setStore($store)->setName('Warehouse ' . $suffix);
		$category = (new Category())->setStore($store)->setName('Category ' . $suffix)->setLevel(1);
		$unit = (new Unit())->setStore($store)->setName('Piece')->setCode('pc-' . $suffix)->setPrecision(0);
		$product = (new Product())->setStore($store)->setCategory($category)->setUnit($unit)->setName('Product ' . $suffix)->setCode('p-' . $suffix)->setCanBeSold(true)->setCanBePurchased(true)->setCanBeManufactured(false)->setProductKind(ProductKindEnum::FINISHED_PRODUCT);
		$stock = (new WarehouseStock())->setWarehouse($warehouse)->setProduct($product)->setQuantityOnHand($storedQuantity)->setAverageCost('10.0000');
		$document = (new InventoryDocument())->setStore($store)->setNumber('ADJ-' . $suffix)->setType(InventoryDocumentType::STOCK_ADJUSTMENT);
		$line = (new InventoryDocumentLine())->setProduct($product)->setWarehouse($warehouse)->setDirection(InventoryDirection::OUT)->setQuantity('1.0000');
		$document->addLine($line);

		foreach ([$currency, $store, $warehouse, $category, $unit, $product, $stock, $document] as $entity) {
			$this->entityManager->persist($entity);
		}
		$this->entityManager->flush();

		return [$stock, $line];
	}

	private function batch(WarehouseStock $stock, string $initial, string $remaining, string $receivedAt): WarehouseStockBatch
	{
		$batch = (new WarehouseStockBatch())
			->setWarehouseStock($stock)
			->setInitialQuantity($initial)
			->setRemainingQuantity($remaining)
			->setUnitCost('10.0000')
			->setReceivedAt(new DateTimeImmutable($receivedAt));
		$stock->addWarehouseStockBatch($batch);
		$this->entityManager->persist($batch);

		return $batch;
	}

	private function movement(
		WarehouseStock $stock,
		InventoryDocumentLine $line,
		?WarehouseStockBatch $batch,
		string $change,
		string $balance,
		string $createdAt,
	): StockMovement {
		$movement = (new StockMovement())
			->setWarehouseStock($stock)
			->setInventoryDocumentLine($line)
			->setWarehouseStockBatch($batch)
			->setQuantityChange($change)
			->setUnitCost('10.0000')
			->setBalanceAfter($balance)
			->setCreatedAt(new DateTimeImmutable($createdAt));
		$line->addStockMovement($movement);
		$this->entityManager->persist($movement);

		return $movement;
	}

	private function uniqueCurrencyCode(): string
	{
		do {
			$code = 'Z' . str_pad(strtoupper(base_convert((string) random_int(0, 1295), 10, 36)), 2, '0', STR_PAD_LEFT);
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
	}
}

<?php

namespace App\Service;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Order;
use App\Entity\Purchase;
use App\Entity\StockMovement;
use App\Entity\Store;
use App\Entity\User\User;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Manager\UserManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class InventoryPostingService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly WarehouseStockService $warehouseStockService,
		private readonly UserManager $userManager,
	)
	{
	}

	public function post(InventoryDocument $document): void
	{
		if ($document->getStatus() !== InventoryDocumentStatus::DRAFT) {
			throw new RuntimeException('Only draft inventory documents can be posted.');
		}

		$this->assertDocumentCanBePosted($document);
		$actor = $this->currentActor();
		$postedAt = new DateTimeImmutable();

		foreach ($document->getLines() as $line) {
			$this->postLine($line);
		}

		$document
			->setStatus(InventoryDocumentStatus::POSTED)
			->setPostedAt($postedAt)
			->setPostedBy($actor)
			->setUpdatedAt($postedAt)
			->setUpdatedBy($actor);

		$this->entityManager->persist($document);
		$this->entityManager->flush();
	}

	public function cancel(InventoryDocument $document): ?InventoryDocument
	{
		if ($document->getStatus() === InventoryDocumentStatus::CANCELED) {
			return null;
		}

		$actor = $this->currentActor();
		$canceledAt = new DateTimeImmutable();
		$reversal = null;

		if ($document->getStatus() === InventoryDocumentStatus::POSTED) {
			$reversal = $this->createReversal($document);
			$this->entityManager->persist($reversal);
			$this->post($reversal);
		}

		$document
			->setStatus(InventoryDocumentStatus::CANCELED)
			->setCanceledAt($canceledAt)
			->setCanceledBy($actor)
			->setUpdatedAt($canceledAt)
			->setUpdatedBy($actor);

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $reversal;
	}

	public function createReversal(InventoryDocument $document): InventoryDocument
	{
		if ($document->getStatus() !== InventoryDocumentStatus::POSTED) {
			throw new RuntimeException('Only posted inventory documents can be reversed.');
		}

		$reversal = (new InventoryDocument())
			->setStore($document->getStore())
			->setNumber($this->reversalNumber($document))
			->setType(InventoryDocumentType::REVERSAL)
			->setDocumentDate(new DateTimeImmutable())
			->setCurrency($document->getCurrency())
			->setExchangeRateToBase($document->getExchangeRateToBase())
			->setTotalAmount($document->getTotalAmount())
			->setTotalAmountBase($document->getTotalAmountBase())
			->setComment(sprintf('Reversal of inventory document %s', $document->getNumber()))
			->setReversedDocument($document)
			->setOrder($document->getOrder())
			->setPurchase($document->getPurchase());

		foreach ($document->getLines() as $line) {
			$reversal->addLine((new InventoryDocumentLine())
				->setQuantity((string) $line->getQuantity())
				->setDirection($this->oppositeDirection($line->getDirection()))
				->setUnitPrice($line->getUnitPrice())
				->setUnitPriceBase($line->getUnitPriceBase())
				->setTotalPrice($line->getTotalPrice())
				->setTotalPriceBase($line->getTotalPriceBase())
				->setProduct($line->getProduct())
				->setWarehouse($line->getWarehouse())
				->setOrderEntry($line->getOrderEntry())
				->setPurchaseEntry($line->getPurchaseEntry()));
		}

		return $reversal;
	}

	private function postLine(InventoryDocumentLine $line): void
	{
		$document = $line->getInventoryDocument();
		$product = $line->getProduct();
		$warehouse = $line->getWarehouse();

		if (!$document || !$product || !$warehouse) {
			throw new RuntimeException('Inventory document line must have document, product and warehouse.');
		}

		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			return;
		}

		$warehouseStock = $this->warehouseStockService->findOrCreate($warehouse, $product);
		$quantity = $this->numberValue($line->getQuantity());
		$unitCost = $this->lineUnitCost($line, $warehouseStock);
		$oldQuantity = $this->numberValue($warehouseStock->getQuantityOnHand());
		$oldAverageCost = $this->numberValue($warehouseStock->getAverageCost());

		if ($line->getDirection() === InventoryDirection::IN) {
			$newQuantity = $oldQuantity + $quantity;
			$newAverageCost = $newQuantity > 0
				? (($oldQuantity * $oldAverageCost) + ($quantity * $unitCost)) / $newQuantity
				: $unitCost;
			$quantityChange = $quantity;

			$warehouseStock
				->setQuantityOnHand($this->formatQuantity($newQuantity))
				->setAverageCost($this->formatMoney($newAverageCost));
		} else {
			$newQuantity = $oldQuantity - $quantity;
			if ($newQuantity < -0.00005) {
				throw new StockOperationException('Quantity on hand cannot be negative.');
			}

			$quantityChange = -$quantity;
			$warehouseStock->setQuantityOnHand($this->formatQuantity(max(0, $newQuantity)));
		}

		$warehouseStock->setUpdatedAt(new DateTimeImmutable());
		$line->setWarehouseStock($warehouseStock);

		$movement = (new StockMovement())
			->setInventoryDocumentLine($line)
			->setWarehouseStock($warehouseStock)
			->setQuantityChange($this->formatQuantity($quantityChange))
			->setUnitCost($this->formatMoney($unitCost))
			->setBalanceAfter($warehouseStock->getQuantityOnHand());

		$line->addStockMovement($movement);
		$this->entityManager->persist($warehouseStock);
		$this->entityManager->persist($movement);
	}

	private function assertDocumentCanBePosted(InventoryDocument $document): void
	{
		$store = $document->getStore();
		if (!$store instanceof Store) {
			throw new RuntimeException('Inventory document store is required.');
		}

		if ($document->getLines()->isEmpty()) {
			throw new RuntimeException('Inventory document must have at least one line.');
		}

		$this->assertSingleBusinessDocument($document);

		foreach ($document->getLines() as $line) {
			$this->assertLineCanBePosted($line, $store);
		}
	}

	private function assertSingleBusinessDocument(InventoryDocument $document): void
	{
		$targetCount = (int) ($document->getOrder() instanceof Order)
			+ (int) ($document->getPurchase() instanceof Purchase);

		if ($targetCount > 1) {
			throw new RuntimeException('Inventory document can be linked to only one business document.');
		}
	}

	private function assertLineCanBePosted(InventoryDocumentLine $line, Store $store): void
	{
		$product = $line->getProduct();
		$warehouse = $line->getWarehouse();
		$quantity = $this->numberValue($line->getQuantity());

		if ($quantity <= 0) {
			throw new RuntimeException('Inventory document line quantity must be greater than zero.');
		}

		if (!$product || $product->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Inventory document line product must belong to document store.');
		}

		if (!$warehouse || $warehouse->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Inventory document line warehouse must belong to document store.');
		}
	}

	private function lineUnitCost(InventoryDocumentLine $line, WarehouseStock $warehouseStock): float
	{
		$value = $line->getUnitPriceBase() ?? $line->getUnitPrice() ?? $warehouseStock->getAverageCost() ?? '0';
		$unitCost = $this->numberValue($value);

		if ($unitCost < 0) {
			throw new RuntimeException('Inventory document line unit cost cannot be negative.');
		}

		return $unitCost;
	}

	private function oppositeDirection(InventoryDirection $direction): InventoryDirection
	{
		return $direction === InventoryDirection::IN ? InventoryDirection::OUT : InventoryDirection::IN;
	}

	private function reversalNumber(InventoryDocument $document): string
	{
		return mb_substr(sprintf('%s-REV-%s', $document->getNumber(), (new DateTimeImmutable())->format('YmdHis')), 0, 255);
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
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

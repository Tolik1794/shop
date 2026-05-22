<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class PurchaseReceiptUseCase
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function createDraft(Purchase $purchase, ?string $number = null): InventoryDocument
	{
		if (!in_array($purchase->getStatus(), [
			PurchaseStatus::ORDERED,
			PurchaseStatus::PARTIALLY_RECEIVED,
		], true)) {
			throw new RuntimeException('Purchase receipt can be created only for ordered or partially received purchases.');
		}

		$document = (new InventoryDocument())
			->setStore($purchase->getStore())
			->setNumber($number ?? $this->number($purchase))
			->setType(InventoryDocumentType::PURCHASE_RECEIPT)
			->setPurchase($purchase)
			->setCurrency($purchase->getCurrency())
			->setExchangeRateToBase($purchase->getExchangeRateToBase())
			->setDocumentDate($purchase->getDocumentDate() ?? new DateTimeImmutable());

		$totalAmount = 0.0;
		$totalAmountBase = 0.0;

		foreach ($purchase->getPurchaseEntries() as $purchaseEntry) {
			$remaining = $this->remainingQuantity($purchaseEntry);

			if ($remaining <= 0.00005) {
				continue;
			}

			$product = $purchaseEntry->getProduct();
			$warehouse = $purchaseEntry->getWarehouse();

			if (!$product || !$warehouse) {
				throw new RuntimeException('Purchase receipt lines require product and warehouse.');
			}

			$quantity = $this->formatQuantity($remaining);
			$unitCost = $purchaseEntry->getUnitCost() ?? '0.0000';
			$unitCostBase = $purchaseEntry->getUnitCostBase() ?? '0.0000';
			$lineTotal = $remaining * $this->numberValue($unitCost);
			$lineTotalBase = $remaining * $this->numberValue($unitCostBase);

			$document->addLine((new InventoryDocumentLine())
				->setProduct($product)
				->setWarehouse($warehouse)
				->setDirection(InventoryDirection::IN)
				->setQuantity($quantity)
				->setUnitPrice($this->formatMoney($this->numberValue($unitCost)))
				->setUnitPriceBase($this->formatMoney($this->numberValue($unitCostBase)))
				->setTotalPrice($this->formatMoney($lineTotal))
				->setTotalPriceBase($this->formatMoney($lineTotalBase))
				->setPurchaseEntry($purchaseEntry));

			$totalAmount += $lineTotal;
			$totalAmountBase += $lineTotalBase;
		}

		if ($document->getLines()->isEmpty()) {
			throw new RuntimeException('Purchase has no remaining quantity to receive.');
		}

		$document
			->setTotalAmount($this->formatMoney($totalAmount))
			->setTotalAmountBase($this->formatMoney($totalAmountBase));

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	public function hasReceivableLines(Purchase $purchase): bool
	{
		if (!in_array($purchase->getStatus(), [
			PurchaseStatus::ORDERED,
			PurchaseStatus::PARTIALLY_RECEIVED,
		], true)) {
			return false;
		}

		foreach ($purchase->getPurchaseEntries() as $purchaseEntry) {
			if ($this->remainingQuantity($purchaseEntry) > 0.00005) {
				return true;
			}
		}

		return false;
	}

	private function remainingQuantity(PurchaseEntry $purchaseEntry): float
	{
		return max(
			0.0,
			$this->numberValue($purchaseEntry->getQuantity()) - $this->numberValue($purchaseEntry->getReceivedQuantity()),
		);
	}

	private function number(Purchase $purchase): string
	{
		return sprintf(
			'PRC-%s-%s',
			$purchase->getId() ?? 'new',
			(new DateTimeImmutable())->format('YmdHisv'),
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

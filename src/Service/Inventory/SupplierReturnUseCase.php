<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\InventoryDocumentLine;
use App\Entity\InventoryReason;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Enum\InventoryDirection;
use App\Enum\InventoryDocumentType;
use App\Service\InventoryPostingService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class SupplierReturnUseCase
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly InventoryPostingService $inventoryPostingService,
	)
	{
	}

	public function createDraft(
		Purchase $purchase,
		PurchaseEntry $purchaseEntry,
		string $quantity,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		if ($purchaseEntry->getPurchase() !== $purchase) {
			throw new RuntimeException('Supplier return purchase entry must belong to the purchase.');
		}

		$product = $purchaseEntry->getProduct();
		$warehouse = $purchaseEntry->getWarehouse();

		if (!$product || !$warehouse) {
			throw new RuntimeException('Supplier return purchase entry must have product and warehouse.');
		}

		$document = (new InventoryDocument())
			->setStore($purchase->getStore())
			->setNumber($number ?? $this->number('SRN'))
			->setType(InventoryDocumentType::SUPPLIER_RETURN)
			->setPurchase($purchase)
			->setReason($reason)
			->addLine((new InventoryDocumentLine())
				->setProduct($product)
				->setWarehouse($warehouse)
				->setDirection(InventoryDirection::OUT)
				->setQuantity($quantity)
				->setUnitPriceBase($purchaseEntry->getUnitCostBase())
				->setPurchaseEntry($purchaseEntry));

		$this->entityManager->persist($document);
		$this->entityManager->flush();

		return $document;
	}

	public function createAndPost(
		Purchase $purchase,
		PurchaseEntry $purchaseEntry,
		string $quantity,
		?InventoryReason $reason = null,
		?string $number = null,
	): InventoryDocument
	{
		$document = $this->createDraft($purchase, $purchaseEntry, $quantity, $reason, $number);
		$this->inventoryPostingService->post($document);

		return $document;
	}

	private function number(string $prefix): string
	{
		return sprintf('%s-%s', $prefix, (new \DateTimeImmutable())->format('YmdHis'));
	}
}

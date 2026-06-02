<?php

namespace App\Service\Inventory;

use App\Entity\InventoryDocument;
use App\Entity\Order;
use App\Repository\InventoryDocumentLineRepository;

class CanceledOrderCustomerReturnDraftService
{
	public function __construct(
		private readonly InventoryDocumentLineRepository $inventoryDocumentLineRepository,
		private readonly CustomerReturnUseCase $customerReturnUseCase,
	)
	{
	}

	public function createDraftIfNeeded(Order $order): ?InventoryDocument
	{
		$draftedOrderEntryIds = $this->inventoryDocumentLineRepository->findDraftCustomerReturnOrderEntryIds($order);
		$returnableEntries = [];

		foreach ($order->getOrderEntries() as $orderEntry) {
			$orderEntryId = $orderEntry->getId();

			if ($orderEntryId !== null && in_array($orderEntryId, $draftedOrderEntryIds, true)) {
				continue;
			}

			if ($this->returnableQuantity($orderEntry->getShippedQuantity(), $orderEntry->getReturnedQuantity()) <= 0.00005) {
				continue;
			}

			$returnableEntries[] = $orderEntry;
		}

		if ($returnableEntries === []) {
			return null;
		}

		return $this->customerReturnUseCase->createDraftForReturnableEntries($order, $returnableEntries);
	}

	private function returnableQuantity(?string $shippedQuantity, ?string $returnedQuantity): float
	{
		return max(
			0.0,
			$this->numberValue($shippedQuantity)
			- $this->numberValue($returnedQuantity),
		);
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}
}

<?php

namespace App\Service\Lifecycle;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\CustomerLabel;
use App\Entity\InventoryReason;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Supplier;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use DateTimeImmutable;
use RuntimeException;

class ReferenceArchivePolicy
{
	public function archive(object $reference, ?DateTimeImmutable $archivedAt = null): void
	{
		$archivedAt ??= new DateTimeImmutable();

		if ($reference instanceof ProductPrice) {
			$reference->setValidTo($archivedAt);

			return;
		}

		if (!$this->supports($reference)) {
			throw new RuntimeException(sprintf('No archive policy is defined for %s.', $reference::class));
		}

		$reference
			->setStatus(ActiveStatusEnum::INACTIVE)
			->setDeletedAt($archivedAt)
			->setUpdatedAt($archivedAt);
	}

	public function supports(object $reference): bool
	{
		return $reference instanceof Product
			|| $reference instanceof Category
			|| $reference instanceof Unit
			|| $reference instanceof Warehouse
			|| $reference instanceof Customer
			|| $reference instanceof CustomerLabel
			|| $reference instanceof Supplier
			|| $reference instanceof InventoryReason
			|| $reference instanceof ProductPrice;
	}
}

<?php

namespace App\Tests\Service\Lifecycle;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\InventoryReason;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Supplier;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ReferenceArchivePolicyTest extends TestCase
{
	private ReferenceArchivePolicy $policy;

	protected function setUp(): void
	{
		$this->policy = new ReferenceArchivePolicy();
	}

	public function testArchivesReferenceEntitiesWithInactiveStatusAndDeletedAt(): void
	{
		$archivedAt = new DateTimeImmutable('2026-05-21 10:00:00');

		foreach ([
			new Product(),
			new Category(),
			new Unit(),
			new Warehouse(),
			new Customer(),
			new Supplier(),
			new InventoryReason(),
		] as $reference) {
			$this->policy->archive($reference, $archivedAt);

			self::assertSame(ActiveStatusEnum::INACTIVE, $reference->getStatus());
			self::assertNotSame(ActiveStatusEnum::DELETED, $reference->getStatus());
			self::assertSame($archivedAt, $reference->getDeletedAt());
			self::assertSame($archivedAt, $reference->getUpdatedAt());
		}
	}

	public function testProductPriceIsClosedInsteadOfHardDeleted(): void
	{
		$archivedAt = new DateTimeImmutable('2026-05-21 10:00:00');
		$productPrice = new ProductPrice();

		$this->policy->archive($productPrice, $archivedAt);

		self::assertSame($archivedAt, $productPrice->getValidTo());
	}

	public function testUnsupportedReferenceIsRejected(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('No archive policy is defined');

		$this->policy->archive(new \stdClass());
	}
}

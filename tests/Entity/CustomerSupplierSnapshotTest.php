<?php

namespace App\Tests\Entity;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Purchase;
use App\Entity\Supplier;
use PHPUnit\Framework\TestCase;

class CustomerSupplierSnapshotTest extends TestCase
{
	public function testOrderCopiesCustomerSnapshot(): void
	{
		$customer = (new Customer())
			->setName('John')
			->setLastName('Customer')
			->setPhone('+380000000001')
			->setEmail('customer@example.com');

		$order = (new Order())->setCustomer($customer);

		self::assertSame('John Customer', $order->getCustomerNameSnapshot());
		self::assertSame('+380000000001', $order->getCustomerPhoneSnapshot());
		self::assertSame('customer@example.com', $order->getCustomerEmailSnapshot());
	}

	public function testCustomerPhoneIsNormalizedToUkrainianInternationalFormat(): void
	{
		$customer = (new Customer())->setPhone('050 123-45-67');

		self::assertSame('+380501234567', $customer->getPhone());
		self::assertSame('+380501234567', Customer::normalizePhone('380 (50) 123-45-67'));
		self::assertSame('+380501234567', Customer::normalizePhone('+380501234567'));
	}

	public function testPurchaseCopiesSupplierSnapshot(): void
	{
		$supplier = (new Supplier())
			->setName('Supplier Ltd')
			->setPhone('+380000000002')
			->setEmail('supplier@example.com');

		$purchase = (new Purchase())->setSupplier($supplier);

		self::assertSame('Supplier Ltd', $purchase->getSupplierNameSnapshot());
		self::assertSame('+380000000002', $purchase->getSupplierPhoneSnapshot());
		self::assertSame('supplier@example.com', $purchase->getSupplierEmailSnapshot());
	}
}

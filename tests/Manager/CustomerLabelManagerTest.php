<?php

namespace App\Tests\Manager;

use App\Entity\Currency;
use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Manager\CustomerLabelManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CustomerLabelManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private CustomerLabelManager $customerLabelManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->customerLabelManager = static::getContainer()->get(CustomerLabelManager::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->customerLabelManager);
	}

	public function testSavePersistsLabel(): void
	{
		$store = $this->persistStore('customer-label-' . uniqid());

		$label = (new CustomerLabel())
			->setStore($store)
			->setCode('vip')
			->setName('VIP')
			->setColor('bg-warning text-dark')
			->setSortOrder(40);

		$this->customerLabelManager->save($label);

		self::assertNotNull($label->getId());
		self::assertSame(ActiveStatusEnum::ACTIVE, $label->getStatus());
		self::assertNull($label->getDeletedAt());
	}

	public function testArchiveSoftDeletesLabel(): void
	{
		$store = $this->persistStore('customer-label-archive-' . uniqid());

		$label = (new CustomerLabel())
			->setStore($store)
			->setCode('problematic')
			->setName('Problematic')
			->setSortOrder(50);

		$this->customerLabelManager->save($label);
		$this->customerLabelManager->archive($label);

		self::assertSame(ActiveStatusEnum::INACTIVE, $label->getStatus());
		self::assertNotNull($label->getDeletedAt());

		// Archived labels are filtered out of the available query builder for the store.
		$available = $this->customerLabelManager->getRepository()
			->findAvailableByStoreQB($store)
			->getQuery()
			->getResult();

		self::assertNotContains($label, $available);
	}

	private function persistStore(string $slug): Store
	{
		$currency = (new Currency())
			->setCode($this->uniqueCurrencyCode())
			->setName('Label currency')
			->setSymbol('$')
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);

		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($currency);

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}

	private function uniqueCurrencyCode(): string
	{
		do {
			$code = 'L' . strtoupper(substr(base_convert((string) random_int(36, 1295), 10, 36), -2));
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
	}
}

<?php

namespace App\Tests\Repository;

use App\Entity\Currency;
use App\Entity\InventoryReason;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType;
use App\Repository\InventoryReasonRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InventoryReasonRepositoryTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private InventoryReasonRepository $repository;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->repository = $this->entityManager->getRepository(InventoryReason::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->repository);
	}

	public function testFindAvailableByStoreUsesDeletedAtAsSoftDeleteMarker(): void
	{
		$store = $this->persistStore('inventory-reason-repository-' . uniqid());
		$active = $this->persistInventoryReason($store, 'Active ' . uniqid(), ActiveStatusEnum::ACTIVE);
		$legacyDeletedStatus = $this->persistInventoryReason($store, 'Legacy deleted status ' . uniqid(), ActiveStatusEnum::DELETED);
		$archived = $this->persistInventoryReason($store, 'Archived ' . uniqid(), ActiveStatusEnum::INACTIVE, new DateTimeImmutable('2026-05-21 10:00:00'));

		$available = $this->repository->findAvailableByStoreQB($store)->getQuery()->getResult();

		self::assertContains($active, $available);
		self::assertContains($legacyDeletedStatus, $available);
		self::assertNotContains($archived, $available);
	}

	private function persistStore(string $slug): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->persistCurrency());

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}

	private function persistCurrency(): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find('UAH');

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode('UAH')
			->setName('Ukrainian hryvnia')
			->setSymbol('UAH')
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}

	private function persistInventoryReason(
		Store $store,
		string $name,
		ActiveStatusEnum $status,
		?DateTimeImmutable $deletedAt = null,
	): InventoryReason {
		$inventoryReason = (new InventoryReason())
			->setStore($store)
			->setName($name)
			->setType(InventoryReasonType::OTHER)
			->setStatus($status)
			->setDeletedAt($deletedAt);

		$this->entityManager->persist($inventoryReason);
		$this->entityManager->flush();

		return $inventoryReason;
	}
}

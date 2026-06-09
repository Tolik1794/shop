<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Currency;
use App\Entity\InventoryDocument;
use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderMaterial;
use App\Entity\ProductionOrderStatus;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use App\Enum\ProductKindEnum;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductionOrderControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testCompletePostsProductionDocumentAndConsumesReservedMaterial(): void
	{
		$this->client->loginUser($this->createUser('production-complete-admin-' . uniqid() . '@example.com'));
		[$store, $order, $materialStock, $output] = $this->createInProgressProductionOrder('production-complete-' . uniqid());
		$orderId = (int) $order->getId();
		$materialStockId = (int) $materialStock->getId();
		$outputId = (int) $output->getId();
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/production/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler->selectButton('Complete')->form();

		$this->client->request(
			$form->getMethod(),
			$form->getUri(),
			$form->getValues(),
			[],
			[
				'HTTP_ACCEPT' => 'application/json',
				'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
			],
		);

		self::assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('Production order action completed.', $data['flashes'][0]['message'] ?? null);
		self::assertArrayHasKey('card', $data['fragments']);

		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$order = $this->entityManager->getRepository(ProductionOrder::class)->find($orderId);
		$materialStock = $this->entityManager->getRepository(WarehouseStock::class)->find($materialStockId);
		$output = $this->entityManager->getRepository(Product::class)->find($outputId);
		self::assertInstanceOf(ProductionOrder::class, $order);
		self::assertInstanceOf(WarehouseStock::class, $materialStock);
		self::assertInstanceOf(Product::class, $output);
		$outputStock = $this->entityManager->getRepository(WarehouseStock::class)->findOneBy([
			'warehouse' => $order->getWarehouse(),
			'product' => $output,
		]);
		$document = $this->entityManager->getRepository(InventoryDocument::class)->findOneBy([
			'productionOrder' => $order,
			'type' => InventoryDocumentType::PRODUCTION,
		]);

		self::assertSame(ProductionOrderStatus::COMPLETED, $order->getStatus());
		self::assertSame('1.0000', $order->getCompletedQuantity());
		self::assertSame('8.0000', $materialStock->getQuantityOnHand());
		self::assertSame('0.0000', $materialStock->getReservedQuantity());
		self::assertInstanceOf(WarehouseStock::class, $outputStock);
		self::assertSame('1.0000', $outputStock->getQuantityOnHand());
		self::assertInstanceOf(InventoryDocument::class, $document);
		self::assertSame(InventoryDocumentStatus::POSTED, $document->getStatus());
	}

	public function testCompleteFailureReturnsUserSafeQuickActionJsonAfterRollback(): void
	{
		$this->client->loginUser($this->createUser('production-complete-failure-admin-' . uniqid() . '@example.com'));
		[$store, $order] = $this->createInProgressProductionOrder('production-complete-failure-' . uniqid());
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/production/order/%d/show', $store->getId(), $order->getId()));
		$form = $crawler->selectButton('Complete')->form();
		$form['completedQuantity'] = '-1';
		$values = $form->getValues();

		$this->client->request(
			$form->getMethod(),
			$form->getUri(),
			$values,
			[],
			[
				'HTTP_ACCEPT' => 'application/json',
				'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
			],
		);

		self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('Completed production quantity must be greater than zero.', $data['flashes'][0]['message'] ?? null);
		self::assertSame([], $data['fragments']);
	}

	private function createUser(string $email): User
	{
		$user = (new User())
			->setEmail($email)
			->setNickname(str_replace(['@', '.'], '-', $email))
			->setFirstName('Admin')
			->setLastName('User')
			->setDateOfBirth(new DateTime('1990-01-01'))
			->setPassword('password')
			->setRoles([RoleEnum::ROLE_SUPER_ADMIN->name]);

		$this->entityManager->persist($user);
		$this->entityManager->flush();

		return $user;
	}

	/**
	 * @return array{Store, ProductionOrder, WarehouseStock, Product}
	 */
	private function createInProgressProductionOrder(string $slug): array
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->createCurrency());
		$category = (new Category())
			->setStore($store)
			->setName('Category ' . $slug)
			->setLevel(1);
		$unit = (new Unit())
			->setStore($store)
			->setCode('pc-' . substr(md5($slug), 0, 8))
			->setName('Piece')
			->setPrecision(0);
		$warehouse = (new Warehouse())
			->setStore($store)
			->setName('Warehouse ' . $slug);
		$output = $this->createProduct($store, $category, $unit, 'output-' . $slug, ProductKindEnum::FINISHED_PRODUCT, true);
		$material = $this->createProduct($store, $category, $unit, 'material-' . $slug, ProductKindEnum::MATERIAL, false);
		$order = (new ProductionOrder())
			->setStore($store)
			->setProduct($output)
			->setWarehouse($warehouse)
			->setStatus(ProductionOrderStatus::IN_PROGRESS)
			->setPlannedQuantity('1.0000')
			->setStartedAt(new DateTimeImmutable())
			->addMaterial((new ProductionOrderMaterial())
				->setMaterial($material)
				->setPlannedQuantity('2.0000'));
		$materialStock = (new WarehouseStock())
			->setWarehouse($warehouse)
			->setProduct($material)
			->setQuantityOnHand('10.0000')
			->setReservedQuantity('2.0000')
			->setAverageCost('5.0000');

		foreach ([$store, $category, $unit, $warehouse, $output, $material, $order, $materialStock] as $entity) {
			$this->entityManager->persist($entity);
		}
		$this->entityManager->flush();

		return [$store, $order, $materialStock, $output];
	}

	private function createProduct(
		Store $store,
		Category $category,
		Unit $unit,
		string $name,
		ProductKindEnum $kind,
		bool $manufacturable,
	): Product {
		return (new Product())
			->setStore($store)
			->setCategory($category)
			->setUnit($unit)
			->setName($name)
			->setCode(substr(md5($name), 0, 16))
			->setCanBeSold($kind === ProductKindEnum::FINISHED_PRODUCT)
			->setCanBePurchased($kind === ProductKindEnum::MATERIAL)
			->setCanBeManufactured($manufacturable)
			->setProductKind($kind);
	}

	private function createCurrency(): Currency
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
}

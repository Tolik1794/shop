<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Currency;
use App\Entity\ProductParameterName;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testCategoryParametersReturnsAllowedParameterNames(): void
	{
		$store = $this->createStore('product-parameter-options-' . uniqid());
		$parent = $this->createCategory($store, 'Furniture');
		$category = $this->createCategory($store, 'Tables', $parent);
		$material = $this->createProductParameterName('Material');
		$width = $this->createProductParameterName('Width');
		$this->createCategoryProductParameterName($parent, $material);
		$this->createCategoryProductParameterName($category, $width, false);

		$this->client->loginUser($this->createUser('product-parameter-options-admin-' . uniqid() . '@example.com'));
		$this->client->request('GET', sprintf(
			'/admin/store/%d/product/category-parameters?category_id=%d',
			$store->getId(),
			$category->getId()
		));

		self::assertResponseIsSuccessful();
		$data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame(['parameters'], array_keys($data));
		self::assertCount(2, $data['parameters']);
		self::assertSame(['Material', 'Width'], array_column($data['parameters'], 'name'));
		self::assertSame([true, false], array_column($data['parameters'], 'isRequired'));
	}

	public function testCategoryParametersRejectsCategoryFromAnotherStore(): void
	{
		$store = $this->createStore('product-parameter-current-store-' . uniqid());
		$otherStore = $this->createStore('product-parameter-other-store-' . uniqid());
		$category = $this->createCategory($otherStore, 'Other store category');

		$this->client->loginUser($this->createUser('product-parameter-scope-admin-' . uniqid() . '@example.com'));
		$this->client->request('GET', sprintf(
			'/admin/store/%d/product/category-parameters?category_id=%d',
			$store->getId(),
			$category->getId()
		));

		self::assertResponseStatusCodeSame(404);
	}

	private function createStore(string $slug): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($this->createCurrency());

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
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

	private function createCategory(Store $store, string $name, ?Category $parent = null): Category
	{
		$category = (new Category())
			->setName($name)
			->setStore($store)
			->setParent($parent)
			->setLevel($parent ? $parent->getLevel() + 1 : 1);

		$this->entityManager->persist($category);
		$this->entityManager->flush();

		return $category;
	}

	private function createProductParameterName(string $name): ProductParameterName
	{
		$parameterName = (new ProductParameterName())
			->setName($name)
			->setDescription($name);

		$this->entityManager->persist($parameterName);
		$this->entityManager->flush();

		return $parameterName;
	}

	private function createCategoryProductParameterName(
		Category $category,
		ProductParameterName $productParameterName,
		bool $isRequired = true
	): CategoryProductParameterName {
		$categoryProductParameterName = (new CategoryProductParameterName())
			->setCategory($category)
			->setProductParameterName($productParameterName)
			->setIsFilter(true)
			->setIsRequired($isRequired);

		$this->entityManager->persist($categoryProductParameterName);
		$this->entityManager->flush();

		return $categoryProductParameterName;
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
}

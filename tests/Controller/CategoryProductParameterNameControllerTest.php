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

class CategoryProductParameterNameControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testIndexRequiresAuthenticatedAdminUser(): void
	{
		$store = $this->createStore('guest-store-' . uniqid());
		$category = $this->createCategory($store, 'Guest category');

		$this->client->request('GET', sprintf(
			'/admin/store/%d/category/%d/product-parameter-name/',
			$store->getId(),
			$category->getId()
		));

		self::assertResponseRedirects('/login');
	}

	public function testIndexDisplaysCategoryProductParameterNames(): void
	{
		$store = $this->createStore('parameter-store-' . uniqid());
		$category = $this->createCategory($store, 'Parameter category');
		$parameterName = $this->createProductParameterName('Material');
		$this->createCategoryProductParameterName($category, $parameterName);

		$this->client->loginUser($this->createUser('category-parameter-admin-' . uniqid() . '@example.com'));

		$this->client->request('GET', sprintf(
			'/admin/store/%d/category/%d/product-parameter-name/',
			$store->getId(),
			$category->getId()
		));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('body', 'Category product parameter names');
		self::assertSelectorTextContains('body', 'Material');
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

	private function createCategory(Store $store, string $name): Category
	{
		$category = (new Category())
			->setName($name)
			->setStore($store)
			->setLevel(1);

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
		ProductParameterName $productParameterName
	): CategoryProductParameterName {
		$categoryProductParameterName = (new CategoryProductParameterName())
			->setCategory($category)
			->setProductParameterName($productParameterName)
			->setIsFilter(true)
			->setIsRequired(true);

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

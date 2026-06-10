<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Currency;
use App\Entity\Permission;
use App\Entity\ProductParameterName;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Entity\User\UserPermissionOverride;
use App\Enum\PermissionOverrideEffect;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testEditFormUsesInlineLocalAndInheritedParameterSections(): void
	{
		$store = $this->createStore('category-form-' . uniqid());
		$parent = $this->createCategory($store, 'Furniture');
		$category = $this->createCategory($store, 'Tables', $parent);
		$this->createCategoryParameter($parent, $this->createParameterName('Material'), true, true);
		$this->createCategoryParameter($category, $this->createParameterName('Width'), false, true);
		$this->client->loginUser($this->createUser('category-form-admin-' . uniqid() . '@example.com'));

		$this->client->request('GET', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('h1', 'Edit category');
		self::assertSelectorExists('.category-form .card');
		self::assertSelectorExists('[data-category-parameters-target~="row"]');
		self::assertSelectorTextContains('.category-parameter-readonly-list', 'Material');
		self::assertSelectorTextContains('.category-parameter-readonly-list', 'Furniture');
		self::assertSelectorExists('.form-actions');
	}

	public function testNewCategorySavesInlineParameters(): void
	{
		$store = $this->createStore('category-inline-save-' . uniqid());
		$parameterName = $this->createParameterName('Height');
		$this->client->loginUser($this->createUser('category-inline-save-admin-' . uniqid() . '@example.com'));
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/category/new', $store->getId()));
		$token = $crawler->filter('input[name="category[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/category/new', $store->getId()), [
			'category' => [
				'name' => 'Shelves',
				'description' => 'Storage',
				'parent' => '',
				'categoryProductParameterNames' => [[
					'productParameterName' => $parameterName->getId(),
					'isRequired' => '1',
					'isFilter' => '1',
				]],
				'_token' => $token,
			],
		]);

		self::assertResponseRedirects();
		$category = $this->entityManager->getRepository(Category::class)->findOneBy(['store' => $store, 'name' => 'Shelves']);
		self::assertInstanceOf(Category::class, $category);
		self::assertCount(1, $category->getCategoryProductParameterNames());
		self::assertSame('Height', $category->getCategoryProductParameterNames()->first()->getProductParameterName()?->getName());
	}

	public function testInheritedParameterConflictRejectsSubmit(): void
	{
		$store = $this->createStore('category-conflict-' . uniqid());
		$parent = $this->createCategory($store, 'Parent');
		$category = $this->createCategory($store, 'Child');
		$parameterName = $this->createParameterName('Material');
		$this->createCategoryParameter($parent, $parameterName, true, true);
		$this->client->loginUser($this->createUser('category-conflict-admin-' . uniqid() . '@example.com'));
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()));
		$token = $crawler->filter('input[name="category[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()), [
			'category' => [
				'name' => $category->getName(),
				'description' => '',
				'parent' => $parent->getId(),
				'categoryProductParameterNames' => [[
					'productParameterName' => $parameterName->getId(),
					'isRequired' => '1',
					'isFilter' => '1',
				]],
				'_token' => $token,
			],
		]);

		self::assertResponseStatusCodeSame(422);
		self::assertSelectorTextContains('body', 'This parameter is already inherited from a parent category.');
	}

	public function testEditCategoryRemovesInlineParameter(): void
	{
		$store = $this->createStore('category-remove-' . uniqid());
		$category = $this->createCategory($store, 'Remove parameter');
		$parameter = $this->createCategoryParameter($category, $this->createParameterName('Depth'), true, true);
		$this->client->loginUser($this->createUser('category-remove-admin-' . uniqid() . '@example.com'));
		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()));
		$token = $crawler->filter('input[name="category[_token]"]')->attr('value');

		$this->client->request('POST', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()), [
			'category' => [
				'name' => $category->getName(),
				'description' => '',
				'parent' => '',
				'categoryProductParameterNames' => [],
				'_token' => $token,
			],
		]);

		self::assertResponseRedirects();
		self::assertNull($this->entityManager->getRepository(CategoryProductParameterName::class)->find($parameter->getId()));
	}

	public function testParameterOptionsReturnInheritedAndAvailableParameters(): void
	{
		$store = $this->createStore('category-options-' . uniqid());
		$parent = $this->createCategory($store, 'Parent');
		$material = $this->createParameterName('Material');
		$this->createParameterName('Width');
		$this->createCategoryParameter($parent, $material, true, false);
		$this->client->loginUser($this->createUser('category-options-admin-' . uniqid() . '@example.com'));

		$this->client->request('GET', sprintf('/admin/store/%d/category/parameter-options?parent_id=%d', $store->getId(), $parent->getId()));

		self::assertResponseIsSuccessful();
		$data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame(['available', 'inherited'], array_keys($data));
		self::assertContains('Width', array_column($data['available'], 'name'));
		self::assertNotContains($material->getId(), array_column($data['available'], 'id'));
		self::assertSame(['Material'], array_column($data['inherited'], 'name'));
		self::assertSame('Parent', $data['inherited'][0]['sourceCategory']);
	}

	public function testParametersAreReadOnlyWithoutCategoryParameterPermission(): void
	{
		$store = $this->createStore('category-readonly-' . uniqid());
		$category = $this->createCategory($store, 'Read only');
		$this->createCategoryParameter($category, $this->createParameterName('Material'), true, true);
		$user = $this->createUser('category-readonly-admin-' . uniqid() . '@example.com');
		$this->denyPermission($user, 'category_parameter.manage');
		$this->client->loginUser($user);

		$crawler = $this->client->request('GET', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()));

		self::assertResponseIsSuccessful();
		self::assertSelectorTextContains('.category-parameter-readonly-list.mb-3 .category-parameter-readonly', 'Material');
		self::assertSelectorNotExists('[data-action="category-parameters#addEntry"]');
		self::assertSelectorNotExists('[name^="category[categoryProductParameterNames]"]');

		$this->client->request('POST', sprintf('/admin/store/%d/category/%d/edit', $store->getId(), $category->getId()), [
			'category' => [
				'name' => $category->getName(),
				'description' => '',
				'parent' => '',
				'categoryProductParameterNames' => [],
				'_token' => $crawler->filter('input[name="category[_token]"]')->attr('value'),
			],
		]);

		self::assertResponseStatusCodeSame(422);
		self::assertCount(1, $this->entityManager->getRepository(CategoryProductParameterName::class)->findBy(['category' => $category]));
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

	private function createParameterName(string $name): ProductParameterName
	{
		$parameterName = (new ProductParameterName())->setName($name)->setDescription($name);
		$this->entityManager->persist($parameterName);
		$this->entityManager->flush();

		return $parameterName;
	}

	private function createCategoryParameter(
		Category $category,
		ProductParameterName $parameterName,
		bool $required,
		bool $filter,
	): CategoryProductParameterName {
		$parameter = (new CategoryProductParameterName())
			->setProductParameterName($parameterName)
			->setIsRequired($required)
			->setIsFilter($filter);
		$category->addCategoryProductParameterName($parameter);
		$this->entityManager->persist($parameter);
		$this->entityManager->flush();

		return $parameter;
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

	private function denyPermission(User $user, string $code): void
	{
		$permission = $this->entityManager->getRepository(Permission::class)->findOneBy(['code' => $code]);
		if (!$permission instanceof Permission) {
			$permission = (new Permission())
				->setCode($code)
				->setName($code)
				->setCategory('Catalog');
			$this->entityManager->persist($permission);
		}

		$user->addPermissionOverride(
			(new UserPermissionOverride())
				->setPermission($permission)
				->setEffect(PermissionOverrideEffect::DENY)
		);
		$this->entityManager->flush();
	}
}

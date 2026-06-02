<?php

namespace App\Tests\Form\Admin\FilterType;

use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Form\Admin\FilterType\CustomerFilterType;
use App\Repository\CustomerRepository;
use App\Service\FilterFormHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class CustomerFilterTypeTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private FormFactoryInterface $formFactory;
	private FilterFormHandler $filterFormHandler;
	private CustomerRepository $customerRepository;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
		$this->filterFormHandler = static::getContainer()->get(FilterFormHandler::class);
		$this->customerRepository = static::getContainer()->get(CustomerRepository::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->formFactory, $this->filterFormHandler, $this->customerRepository);
	}

	public function testLabelFilterReturnsMatchingCustomersWithoutDuplicates(): void
	{
		$store = $this->persistStore('customer-filter-' . uniqid());

		$labelA = $this->persistLabel($store, 'vip-' . uniqid());
		$labelB = $this->persistLabel($store, 'regular-' . uniqid());

		$withA = $this->persistCustomer($store, 'WithA');
		$withA->addLabel($labelA);

		$withBoth = $this->persistCustomer($store, 'WithBoth');
		$withBoth->addLabel($labelA);
		$withBoth->addLabel($labelB);

		$withNone = $this->persistCustomer($store, 'WithNone');
		$this->entityManager->flush();

		$queryBuilder = $this->customerRepository->findAvailableByStoreQB($store);

		$form = $this->formFactory->create(CustomerFilterType::class, null, ['store' => $store, 'csrf_protection' => false]);
		$form->submit(['labels' => [(string) $labelA->getId()]]);

		self::assertTrue($form->isSubmitted());
		self::assertTrue($form->isValid());

		$this->filterFormHandler->handleFilterForm($form, $queryBuilder);

		$results = $queryBuilder->getQuery()->getResult();

		self::assertContains($withA, $results);
		self::assertContains($withBoth, $results);
		self::assertNotContains($withNone, $results);
		// The many-to-many join must not duplicate a customer that has the label.
		self::assertSame(1, count(array_filter($results, static fn (Customer $c): bool => $c === $withBoth)));
	}

	private function persistLabel(Store $store, string $code): CustomerLabel
	{
		$label = (new CustomerLabel())
			->setStore($store)
			->setCode($code)
			->setName(ucfirst($code))
			->setSortOrder(10);

		$this->entityManager->persist($label);

		return $label;
	}

	private function persistCustomer(Store $store, string $name): Customer
	{
		$customer = (new Customer())
			->setStore($store)
			->setName($name)
			->setLastName('Filter');

		$this->entityManager->persist($customer);

		return $customer;
	}

	private function persistStore(string $slug): Store
	{
		$currency = (new Currency())
			->setCode($this->uniqueCurrencyCode())
			->setName('Filter currency')
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
			$code = 'F' . strtoupper(substr(base_convert((string) random_int(36, 1295), 10, 36), -2));
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
	}
}

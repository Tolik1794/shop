<?php

namespace App\Tests\Service\Customer;

use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use App\Manager\OrderCommentManager;
use App\Manager\OrderManager;
use App\Service\Customer\CustomerHistoryProvider;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CustomerHistoryProviderTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;
	private OrderCommentManager $orderCommentManager;
	private CustomerHistoryProvider $customerHistoryProvider;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
		$this->orderCommentManager = static::getContainer()->get(OrderCommentManager::class);
		$this->customerHistoryProvider = static::getContainer()->get(CustomerHistoryProvider::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager, $this->orderCommentManager, $this->customerHistoryProvider);
	}

	public function testForCustomerCollectsLabelsOrdersAndImportantComments(): void
	{
		$store = $this->persistStore('customer-history-' . uniqid());
		$author = $this->persistUser('customer-history-author-' . uniqid() . '@example.com');

		$label = (new CustomerLabel())
			->setStore($store)
			->setCode('vip')
			->setName('VIP')
			->setSortOrder(10);
		$this->entityManager->persist($label);

		$customer = (new Customer())
			->setStore($store)
			->setName('Historic')
			->setLastName('Customer');
		$customer->addLabel($label);
		$this->entityManager->persist($customer);
		$this->entityManager->flush();

		$order = $this->orderManager->createDraft($store);
		$order->setCustomer($customer);
		$this->orderManager->saveOrder($order);

		$importantComment = $this->orderCommentManager->create($order, $author, 'Call before delivery', CommentTypeEnum::GENERAL, true);
		$this->orderCommentManager->create($order, $author, 'Just a note');

		$history = $this->customerHistoryProvider->forCustomer($customer);

		self::assertContains($label, $history['labels']);
		self::assertContains($order, $history['recent_orders']);
		self::assertContains($importantComment, $history['important_comments']);
		self::assertCount(1, $history['important_comments']);
		self::assertSame([], $history['returns']);
	}

	private function persistStore(string $slug): Store
	{
		$currency = (new Currency())
			->setCode($this->uniqueCurrencyCode())
			->setName('History currency')
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

	private function persistUser(string $email): User
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

	private function uniqueCurrencyCode(): string
	{
		do {
			$code = 'H' . strtoupper(substr(base_convert((string) random_int(36, 1295), 10, 36), -2));
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
	}
}

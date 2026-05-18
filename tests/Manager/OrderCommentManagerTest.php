<?php

namespace App\Tests\Manager;

use App\Entity\Currency;
use App\Entity\OrderHistory;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Manager\OrderCommentManager;
use App\Manager\OrderManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderCommentManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;
	private OrderCommentManager $orderCommentManager;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
		$this->orderCommentManager = static::getContainer()->get(OrderCommentManager::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager, $this->orderCommentManager);
	}

	public function testCommentLifecycleCreatesAppendOnlyHistory(): void
	{
		$currency = $this->persistCurrency('M' . substr(uniqid(), -2), 'Comment currency');
		$store = $this->persistStore('order-comment-' . uniqid(), $currency);
		$author = $this->persistUser('order-comment-author-' . uniqid() . '@example.com');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$comment = $this->orderCommentManager->create($order, $author, 'Initial note');
		$this->orderCommentManager->edit($comment, $author, 'Updated note');
		$this->orderCommentManager->softDelete($comment, $author);

		self::assertSame([], $this->orderCommentManager->getRepository()->findVisibleByOrder($order));
		self::assertNotNull($comment->getDeletedAt());

		$history = $this->entityManager->getRepository(OrderHistory::class)->findBy(
			['order' => $order],
			['id' => 'ASC'],
		);
		$eventKeys = array_map(static fn (OrderHistory $entry): string => $entry->getEventKey(), $history);

		self::assertContains('order.comment_added', $eventKeys);
		self::assertContains('order.comment_edited', $eventKeys);
		self::assertContains('order.comment_deleted', $eventKeys);

		$editedHistory = $this->entityManager->getRepository(OrderHistory::class)->findOneBy([
			'order' => $order,
			'eventKey' => 'order.comment_edited',
		]);

		self::assertInstanceOf(OrderHistory::class, $editedHistory);
		self::assertSame('Admin User', $editedHistory->getActorNameSnapshot());
		self::assertSame([
			'body' => ['from' => 'Initial note', 'to' => 'Updated note'],
		], $editedHistory->getChanges());
	}

	private function persistCurrency(string $code, string $name): Currency
	{
		$currency = (new Currency())
			->setCode($code)
			->setName($name)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}

	private function persistStore(string $slug, Currency $baseCurrency): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($baseCurrency);

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
}

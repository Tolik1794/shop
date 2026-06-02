<?php

namespace App\Tests\Manager;

use App\Entity\Currency;
use App\Entity\OrderHistory;
use App\Entity\Store;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use App\Manager\OrderCommentManager;
use App\Manager\OrderManager;
use App\Repository\OrderCommentReadStateRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderCommentManagerTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private OrderManager $orderManager;
	private OrderCommentManager $orderCommentManager;
	private OrderCommentReadStateRepository $readStateRepository;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->orderManager = static::getContainer()->get(OrderManager::class);
		$this->orderCommentManager = static::getContainer()->get(OrderCommentManager::class);
		$this->readStateRepository = static::getContainer()->get(OrderCommentReadStateRepository::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->orderManager, $this->orderCommentManager, $this->readStateRepository);
	}

	public function testCommentLifecycleCreatesAppendOnlyHistory(): void
	{
		$currency = $this->persistCurrency($this->uniqueCurrencyCode(), 'Comment currency');
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

	public function testCreateStoresTypeAndImportance(): void
	{
		$currency = $this->persistCurrency($this->uniqueCurrencyCode(), 'Comment currency');
		$store = $this->persistStore('order-comment-type-' . uniqid(), $currency);
		$author = $this->persistUser('order-comment-type-author-' . uniqid() . '@example.com');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);

		$comment = $this->orderCommentManager->create($order, $author, 'Warehouse note', CommentTypeEnum::WAREHOUSE, true);

		self::assertSame(CommentTypeEnum::WAREHOUSE, $comment->getType());
		self::assertTrue($comment->isImportant());

		$this->orderCommentManager->edit($comment, $author, 'Warehouse note', CommentTypeEnum::ACCOUNTING, false);

		self::assertSame(CommentTypeEnum::ACCOUNTING, $comment->getType());
		self::assertFalse($comment->isImportant());
	}

	public function testCountUnreadByOrdersRespectsAuthorRelevanceImportanceAndReadState(): void
	{
		$currency = $this->persistCurrency($this->uniqueCurrencyCode(), 'Comment currency');
		$store = $this->persistStore('order-comment-unread-' . uniqid(), $currency);
		$author = $this->persistUser('order-comment-unread-author-' . uniqid() . '@example.com');
		$reader = $this->persistUser('order-comment-unread-reader-' . uniqid() . '@example.com');
		$order = $this->orderManager->createDraft($store);
		$this->orderManager->saveOrder($order);
		$orderId = $order->getId();

		$this->orderCommentManager->create($order, $author, 'General by author', CommentTypeEnum::GENERAL);
		$this->orderCommentManager->create($order, $author, 'Warehouse by author', CommentTypeEnum::WAREHOUSE);
		$this->orderCommentManager->create($order, $author, 'Accounting important by author', CommentTypeEnum::ACCOUNTING, true);
		$this->orderCommentManager->create($order, $reader, 'General by reader', CommentTypeEnum::GENERAL);

		// Reader is relevant only to GENERAL comments: counts general (relevant) + accounting (important),
		// excludes warehouse (irrelevant) and the reader's own comment.
		$readerCounts = $this->readStateRepository->countUnreadByOrders([$orderId], $reader, [CommentTypeEnum::GENERAL]);
		self::assertSame(2, $readerCounts[$orderId] ?? 0);

		// Author never counts their own comments; only the reader-authored general one remains.
		$authorCounts = $this->readStateRepository->countUnreadByOrders([$orderId], $author, CommentTypeEnum::cases());
		self::assertSame(1, $authorCounts[$orderId] ?? 0);

		// After the reader views the order, nothing existing is unread.
		$this->orderCommentManager->markOrderRead($order, $reader);
		$afterRead = $this->readStateRepository->countUnreadByOrders([$orderId], $reader, [CommentTypeEnum::GENERAL]);
		self::assertSame(0, $afterRead[$orderId] ?? 0);

		// A new comment created strictly after the read time becomes unread again.
		$fresh = $this->orderCommentManager->create($order, $author, 'Fresh general', CommentTypeEnum::GENERAL);
		$fresh->setCreatedAt(new DateTimeImmutable('+10 seconds'));
		$this->entityManager->flush();

		$afterNew = $this->readStateRepository->countUnreadByOrders([$orderId], $reader, [CommentTypeEnum::GENERAL]);
		self::assertSame(1, $afterNew[$orderId] ?? 0);
	}

	private function uniqueCurrencyCode(): string
	{
		do {
			$code = 'M' . strtoupper(substr(base_convert((string) random_int(36, 1295), 10, 36), -2));
		} while ($this->entityManager->getRepository(Currency::class)->find($code) instanceof Currency);

		return $code;
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

<?php

namespace App\Manager;

use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\User\User;
use App\Repository\OrderCommentRepository;
use App\Workflow\History\OrderHistoryRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class OrderCommentManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly OrderHistoryRecorder $orderHistoryRecorder,
	)
	{
	}

	public function create(Order $order, User $author, string $body): OrderComment
	{
		$body = $this->normalizeBody($body);
		$comment = (new OrderComment())
			->setOrder($order)
			->setAuthor($author)
			->setBody($body);

		$this->entityManager->persist($comment);
		$this->orderHistoryRecorder->recordCommentAdded($comment, $author);
		$this->entityManager->flush();

		return $comment;
	}

	public function edit(OrderComment $comment, User $actor, string $body): void
	{
		$body = $this->normalizeBody($body);
		$before = $comment->getBody();

		if ($before === $body) {
			return;
		}

		$comment
			->setBody($body)
			->setUpdatedAt(new DateTimeImmutable());

		$this->orderHistoryRecorder->recordCommentEdited($comment, $actor, $before);
		$this->entityManager->flush();
	}

	public function softDelete(OrderComment $comment, User $actor): void
	{
		if ($comment->getDeletedAt() instanceof DateTimeImmutable) {
			return;
		}

		$comment->setDeletedAt(new DateTimeImmutable());
		$this->orderHistoryRecorder->recordCommentDeleted($comment, $actor);
		$this->entityManager->flush();
	}

	public function getRepository(): OrderCommentRepository
	{
		return $this->entityManager->getRepository(OrderComment::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function normalizeBody(string $body): string
	{
		$body = trim($body);

		if ($body === '') {
			throw new RuntimeException('Order comment must not be blank.');
		}

		return $body;
	}
}

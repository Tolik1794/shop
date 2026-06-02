<?php

namespace App\Manager;

use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use App\Repository\OrderCommentReadStateRepository;
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
		private readonly OrderCommentReadStateRepository $readStateRepository,
	)
	{
	}

	public function create(
		Order $order,
		User $author,
		string $body,
		CommentTypeEnum $type = CommentTypeEnum::GENERAL,
		bool $isImportant = false,
	): OrderComment
	{
		$body = $this->normalizeBody($body);
		$comment = (new OrderComment())
			->setOrder($order)
			->setAuthor($author)
			->setBody($body)
			->setType($type)
			->setIsImportant($isImportant);

		$this->entityManager->persist($comment);
		$this->orderHistoryRecorder->recordCommentAdded($comment, $author);
		$this->entityManager->flush();

		return $comment;
	}

	public function edit(
		OrderComment $comment,
		User $actor,
		string $body,
		?CommentTypeEnum $type = null,
		?bool $isImportant = null,
	): void
	{
		$body = $this->normalizeBody($body);
		$before = $comment->getBody();
		$typeChanged = $type !== null && $type !== $comment->getType();
		$importanceChanged = $isImportant !== null && $isImportant !== $comment->isImportant();

		if ($before === $body && !$typeChanged && !$importanceChanged) {
			return;
		}

		if ($type !== null) {
			$comment->setType($type);
		}

		if ($isImportant !== null) {
			$comment->setIsImportant($isImportant);
		}

		$comment
			->setBody($body)
			->setUpdatedAt(new DateTimeImmutable());

		if ($before !== $body) {
			$this->orderHistoryRecorder->recordCommentEdited($comment, $actor, $before);
		}

		$this->entityManager->flush();
	}

	public function markOrderRead(Order $order, User $user): void
	{
		$this->readStateRepository->markRead($order, $user);
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

<?php

namespace App\Workflow\History;

use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\OrderHistory;
use App\Entity\OrderHistorySource;
use App\Entity\User\User;
use App\Workflow\HistoryRecorderInterface;
use App\Workflow\TransitionContext;
use App\Workflow\TransitionDefinition;
use App\Workflow\TransitionResult;
use App\Workflow\WorkflowSubjectInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Records the append-only order activity timeline.
 */
class OrderHistoryRecorder implements HistoryRecorderInterface
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function record(
		WorkflowSubjectInterface $subject,
		TransitionDefinition $transition,
		TransitionContext $context,
		TransitionResult $result,
	): void
	{
		if (!$subject instanceof Order) {
			throw new LogicException(sprintf('Expected "%s", got "%s".', Order::class, $subject::class));
		}

		$this->create(
			order: $subject,
			eventKey: $transition->historyEventKey,
			source: $this->resolveSource($context->source),
			title: 'Order status changed',
			description: sprintf('Status changed from %s to %s.', $result->fromStatus, $result->toStatus),
			changes: ['status' => ['from' => $result->fromStatus, 'to' => $result->toStatus]],
			payload: $context->payload,
			actor: $context->actor,
			occurredAt: $context->occurredAt,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function recordCreated(Order $order, ?User $actor, array $payload = []): void
	{
		$this->create($order, 'order.created', $this->sourceForActor($actor), 'Order created', payload: $payload, actor: $actor);
	}

	/**
	 * @param array<string, array{from: mixed, to: mixed}> $changes
	 */
	public function recordUpdated(Order $order, array $changes, ?User $actor): void
	{
		if ($changes === []) {
			return;
		}

		$this->create($order, 'order.updated', $this->sourceForActor($actor), 'Order updated', changes: $changes, actor: $actor);
	}

	/**
	 * @param array<string, array{from: mixed, to: mixed}> $changes
	 */
	public function recordCustomerChanged(Order $order, array $changes, ?User $actor): void
	{
		$this->create($order, 'order.customer_changed', $this->sourceForActor($actor), 'Order customer changed', changes: $changes, actor: $actor);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function recordEntryAdded(Order $order, array $payload, ?User $actor, ?int $entryId = null): void
	{
		$this->create($order, 'order.entry_added', $this->sourceForActor($actor), 'Order entry added', payload: $payload, relatedEntityType: 'OrderEntry', relatedEntityId: $entryId, actor: $actor);
	}

	/**
	 * @param array<string, array{from: mixed, to: mixed}> $changes
	 */
	public function recordEntryUpdated(Order $order, array $changes, ?User $actor, ?int $entryId = null): void
	{
		if ($changes === []) {
			return;
		}

		$this->create($order, 'order.entry_updated', $this->sourceForActor($actor), 'Order entry updated', changes: $changes, relatedEntityType: 'OrderEntry', relatedEntityId: $entryId, actor: $actor);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function recordEntryRemoved(Order $order, array $payload, ?User $actor, ?int $entryId = null): void
	{
		$this->create($order, 'order.entry_removed', $this->sourceForActor($actor), 'Order entry removed', payload: $payload, relatedEntityType: 'OrderEntry', relatedEntityId: $entryId, actor: $actor);
	}

	public function recordCommentAdded(OrderComment $comment, User $actor): void
	{
		$this->create($comment->getOrder(), 'order.comment_added', OrderHistorySource::MANUAL, 'Comment added', relatedEntityType: 'OrderComment', relatedEntityId: $comment->getId(), actor: $actor);
	}

	public function recordCommentEdited(OrderComment $comment, User $actor, string $before): void
	{
		$this->create(
			$comment->getOrder(),
			'order.comment_edited',
			OrderHistorySource::MANUAL,
			'Comment edited',
			changes: ['body' => ['from' => $before, 'to' => $comment->getBody()]],
			relatedEntityType: 'OrderComment',
			relatedEntityId: $comment->getId(),
			actor: $actor,
		);
	}

	public function recordCommentDeleted(OrderComment $comment, User $actor): void
	{
		$this->create($comment->getOrder(), 'order.comment_deleted', OrderHistorySource::MANUAL, 'Comment deleted', relatedEntityType: 'OrderComment', relatedEntityId: $comment->getId(), actor: $actor);
	}

	/**
	 * @param array<string, array{from: mixed, to: mixed}>|null $changes
	 * @param array<string, mixed> $payload
	 */
	private function create(
		Order $order,
		string $eventKey,
		OrderHistorySource $source,
		string $title,
		?string $description = null,
		?array $changes = null,
		array $payload = [],
		?string $relatedEntityType = null,
		?int $relatedEntityId = null,
		?User $actor = null,
		?DateTimeImmutable $occurredAt = null,
	): OrderHistory
	{
		$history = (new OrderHistory())
			->setOrder($order)
			->setEventKey($eventKey)
			->setSource($source)
			->setTitle($title)
			->setDescription($description)
			->setChanges($changes)
			->setPayload($payload !== [] ? $payload : null)
			->setRelatedEntityType($relatedEntityType)
			->setRelatedEntityId($relatedEntityId)
			->setOccurredAt($occurredAt ?? new DateTimeImmutable())
			->setActor($actor)
			->setActorNameSnapshot($this->actorName($actor));

		$this->entityManager->persist($history);

		return $history;
	}

	private function sourceForActor(?User $actor): OrderHistorySource
	{
		return $actor instanceof User ? OrderHistorySource::MANUAL : OrderHistorySource::SYSTEM;
	}

	private function resolveSource(string $source): OrderHistorySource
	{
		return OrderHistorySource::tryFrom($source) ?? OrderHistorySource::SYSTEM;
	}

	private function actorName(?User $actor): ?string
	{
		if (!$actor instanceof User) {
			return null;
		}

		$name = trim(sprintf('%s %s', $actor->getFirstName() ?? '', $actor->getLastName() ?? ''));

		return $name !== '' ? $name : ($actor->getNickname() ?? $actor->getEmail());
	}
}

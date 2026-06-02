<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderComment;
use App\Entity\OrderCommentReadState;
use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderCommentReadState>
 */
class OrderCommentReadStateRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, OrderCommentReadState::class);
	}

	public function markRead(Order $order, User $user): void
	{
		$state = $this->findOneBy(['order' => $order, 'user' => $user]);

		if (!$state instanceof OrderCommentReadState) {
			$state = (new OrderCommentReadState())
				->setOrder($order)
				->setUser($user);
			$this->getEntityManager()->persist($state);
		}

		$state->setLastReadAt(new DateTimeImmutable());
		$this->getEntityManager()->flush();
	}

	/**
	 * Counts unread comments per order for the given user.
	 * A comment is unread when it is newer than the user's last read time (or never read),
	 * authored by someone else, and either important or of a type relevant to the user.
	 *
	 * @param int[] $orderIds
	 * @param CommentTypeEnum[] $relevantTypes
	 *
	 * @return array<int, int> map of orderId => unread count
	 */
	public function countUnreadByOrders(array $orderIds, User $user, array $relevantTypes): array
	{
		if ($orderIds === []) {
			return [];
		}

		$typeValues = array_map(static fn (CommentTypeEnum $type): string => $type->value, $relevantTypes);

		$rows = $this->getEntityManager()->createQueryBuilder()
			->select('IDENTITY(comment.order) AS orderId', 'COUNT(comment.id) AS unreadCount')
			->from(OrderComment::class, 'comment')
			->leftJoin(
				OrderCommentReadState::class,
				'state',
				'WITH',
				'state.order = comment.order AND state.user = :user'
			)
			->andWhere('IDENTITY(comment.order) IN (:orderIds)')
			->andWhere('comment.deletedAt IS NULL')
			->andWhere('comment.author != :user')
			->andWhere('state.lastReadAt IS NULL OR comment.createdAt > state.lastReadAt')
			->andWhere('comment.isImportant = true OR comment.type IN (:types)')
			->setParameter('user', $user)
			->setParameter('orderIds', $orderIds)
			->setParameter('types', $typeValues === [] ? [''] : $typeValues)
			->groupBy('comment.order')
			->getQuery()
			->getResult();

		$counts = [];

		foreach ($rows as $row) {
			$counts[(int) $row['orderId']] = (int) $row['unreadCount'];
		}

		return $counts;
	}
}

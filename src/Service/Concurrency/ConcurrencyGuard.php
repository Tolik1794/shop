<?php

namespace App\Service\Concurrency;

use App\Exception\ConcurrencyConflictException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\PessimisticLockException;

class ConcurrencyGuard
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	/**
	 * Re-reads the current database row under a write lock.
	 */
	public function lock(object $entity): object
	{
		if (!$this->entityManager->getConnection()->isTransactionActive()) {
			throw new ConcurrencyConflictException('Concurrent write protection requires an active transaction.');
		}

		try {
			$this->entityManager->lock($entity, LockMode::PESSIMISTIC_WRITE);
			$this->entityManager->refresh($entity);
		} catch (RetryableException|OptimisticLockException|PessimisticLockException) {
			throw new ConcurrencyConflictException();
		}

		return $entity;
	}

	/**
	 * @param iterable<object|null> $entities
	 */
	public function lockAll(iterable $entities): void
	{
		$lockable = [];

		foreach ($entities as $entity) {
			if (!is_object($entity)) {
				continue;
			}

			$id = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);
			if ($id === []) {
				continue;
			}

			$lockable[] = [
				'key' => sprintf('%s:%s', $entity::class, implode(':', array_map('strval', $id))),
				'entity' => $entity,
			];
		}

		usort($lockable, static fn (array $left, array $right): int => $left['key'] <=> $right['key']);

		foreach ($lockable as $item) {
			$this->lock($item['entity']);
		}
	}

	public function assertSubmittedVersion(object $entity, mixed $submittedVersion): void
	{
		if (!method_exists($entity, 'getVersion')) {
			return;
		}

		if ((int) $submittedVersion !== (int) $entity->getVersion()) {
			throw new ConcurrencyConflictException();
		}
	}
}

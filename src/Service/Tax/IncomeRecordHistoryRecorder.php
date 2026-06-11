<?php

namespace App\Service\Tax;

use App\Entity\IncomeRecord;
use App\Entity\IncomeRecordHistory;
use App\Entity\User\User;
use App\Enum\IncomeClassificationEnum;
use Doctrine\ORM\EntityManagerInterface;

class IncomeRecordHistoryRecorder
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function recordRecognized(IncomeRecord $record, ?User $actor): void
	{
		$this->record($record, $actor, 'income.recognized', [
			'source_type' => $record->getSourceType()->value,
			'source_id' => $record->getSourceId(),
			'classification' => $record->getClassification()->value,
			'amount_uah' => $record->getAmountUah(),
		]);
	}

	public function recordReclassified(
		IncomeRecord $record,
		IncomeClassificationEnum $oldClassification,
		IncomeClassificationEnum $newClassification,
		?string $comment,
		?User $actor,
	): void
	{
		$this->record($record, $actor, 'income.reclassified', [
			'old_classification' => $oldClassification->value,
			'new_classification' => $newClassification->value,
			'comment' => $comment,
		]);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function record(IncomeRecord $record, ?User $actor, string $eventKey, array $payload): void
	{
		$history = (new IncomeRecordHistory())
			->setIncomeRecord($record)
			->setEventKey($eventKey)
			->setPayload($payload !== [] ? $payload : null)
			->setActor($actor)
			->setActorNameSnapshot($this->actorName($actor));

		$this->entityManager->persist($history);
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

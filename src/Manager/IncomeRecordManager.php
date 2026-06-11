<?php

namespace App\Manager;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\User\User;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Service\Tax\IncomeRecordHistoryRecorder;
use App\Service\Tax\NbuExchangeRateProvider;
use App\Service\Tax\TaxPeriodGuard;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class IncomeRecordManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly NbuExchangeRateProvider $nbuExchangeRateProvider,
		private readonly IncomeRecordHistoryRecorder $incomeRecordHistoryRecorder,
		private readonly UserManager $userManager,
		private readonly TaxPeriodGuard $taxPeriodGuard,
	)
	{
	}

	public function saveManual(IncomeRecord $record): void
	{
		$actor = $this->currentActor();
		$currency = $record->getCurrency();
		$recognizedAt = $record->getRecognizedAt();

		if (!$currency instanceof Currency || !$recognizedAt instanceof DateTimeImmutable) {
			throw new RuntimeException('Manual income record requires currency and recognition date.');
		}

		$legalEntity = $record->getLegalEntity();
		if ($legalEntity !== null) {
			$this->taxPeriodGuard->assertOpen($legalEntity, $recognizedAt);
		}

		$rate = $this->nbuExchangeRateProvider->getRate($currency, $recognizedAt);
		if ($rate === null) {
			throw new RuntimeException(sprintf(
				'NBU rate %s -> UAH for %s is missing. Run app:tax:nbu-rates-sync first.',
				$currency->getCode(),
				$recognizedAt->format('Y-m-d')
			));
		}

		$record
			->setSourceType(IncomeSourceTypeEnum::MANUAL)
			->setSourceId(null)
			->setNbuExchangeRate($rate)
			->setAmountUah(number_format((float) $record->getAmount() * (float) $rate, 4, '.', ''))
			->setCreatedBy($actor)
			->setUpdatedBy($actor);

		$this->entityManager->persist($record);
		$this->incomeRecordHistoryRecorder->recordRecognized($record, $actor);
		$this->entityManager->flush();
	}

	public function reclassify(IncomeRecord $record, IncomeClassificationEnum $classification, ?string $comment): void
	{
		$oldClassification = $record->getClassification();

		if ($oldClassification === $classification) {
			return;
		}

		$legalEntity = $record->getLegalEntity();
		$recognizedAt = $record->getRecognizedAt();
		if ($legalEntity !== null && $recognizedAt instanceof DateTimeImmutable) {
			$this->taxPeriodGuard->assertOpen($legalEntity, $recognizedAt);
		}

		$actor = $this->currentActor();

		$record
			->setClassification($classification)
			->setUpdatedBy($actor)
			->setUpdatedAt(new DateTimeImmutable());

		$this->incomeRecordHistoryRecorder->recordReclassified($record, $oldClassification, $classification, $comment, $actor);
		$this->entityManager->flush();
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
	}
}

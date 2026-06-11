<?php

namespace App\Service\Tax;

use App\Dto\Tax\RecognitionOutcome;
use App\Entity\Payment;
use App\Enum\IncomeClassificationEnum;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Backfills IncomeRecord projections for historical payments.
 * Report-only by default; pass apply=true to persist. Idempotent thanks to
 * the already_recognized check and the unique (source_type, source_id) index.
 */
class IncomeBackfillService
{
	private const int BATCH_SIZE = 200;

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly IncomeRecognitionService $incomeRecognitionService,
	)
	{
	}

	/**
	 * @return array{
	 *     scanned: int,
	 *     created: array<string, int>,
	 *     skipped: array<string, int>,
	 *     totals: array<string, array<int, string>>,
	 *     stores_without_legal_entity: list<string>
	 * }
	 */
	public function backfill(bool $apply, ?int $storeId = null): array
	{
		$scanned = 0;
		$created = [];
		$skipped = [];
		$totals = [];
		$storesWithoutLegalEntity = [];

		$lastId = 0;

		while (true) {
			$payments = $this->nextBatch($lastId, $storeId);
			if ($payments === []) {
				break;
			}

			foreach ($payments as $payment) {
				$scanned++;
				$lastId = (int) $payment->getId();

				$outcome = $this->incomeRecognitionService->recognizePayment($payment, null, $apply);

				if ($outcome->isCreated()) {
					$record = $outcome->record;
					$classification = $record->getClassification();
					$created[$classification->value] = ($created[$classification->value] ?? 0) + 1;

					$entityName = (string) $record->getLegalEntity();
					$year = (int) $record->getRecognizedAt()?->format('Y');
					$current = $totals[$entityName][$year] ?? '0.0000';
					$signedAmount = $classification === IncomeClassificationEnum::REFUND
						? -1 * (float) $record->getAmountUah()
						: ($classification === IncomeClassificationEnum::INCOME ? (float) $record->getAmountUah() : 0.0);
					$totals[$entityName][$year] = number_format((float) $current + $signedAmount, 4, '.', '');
				} else {
					$skipped[(string) $outcome->reason] = ($skipped[(string) $outcome->reason] ?? 0) + 1;

					if ($outcome->reason === RecognitionOutcome::REASON_NO_LEGAL_ENTITY) {
						$storeName = (string) $payment->getStore()?->getName();
						if (!in_array($storeName, $storesWithoutLegalEntity, true)) {
							$storesWithoutLegalEntity[] = $storeName;
						}
					}
				}
			}

			if ($apply) {
				$this->entityManager->flush();
				$this->entityManager->clear();
			}
		}

		ksort($created);
		ksort($skipped);
		ksort($totals);

		return [
			'scanned' => $scanned,
			'created' => $created,
			'skipped' => $skipped,
			'totals' => $totals,
			'stores_without_legal_entity' => $storesWithoutLegalEntity,
		];
	}

	/**
	 * @return Payment[]
	 */
	private function nextBatch(int $lastId, ?int $storeId): array
	{
		$queryBuilder = $this->entityManager->getRepository(Payment::class)
			->createQueryBuilder('payment')
			->andWhere('payment.id > :lastId')
			->setParameter('lastId', $lastId)
			->orderBy('payment.id', 'ASC')
			->setMaxResults(self::BATCH_SIZE);

		if ($storeId !== null) {
			$queryBuilder
				->andWhere('payment.store = :storeId')
				->setParameter('storeId', $storeId);
		}

		return $queryBuilder->getQuery()->getResult();
	}
}

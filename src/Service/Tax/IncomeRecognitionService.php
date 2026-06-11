<?php

namespace App\Service\Tax;

use App\Dto\Tax\RecognitionOutcome;
use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\Payment;
use App\Entity\User\User;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Enum\PaymentDirectionEnum;
use App\Repository\IncomeRecordRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Projects recognized income (cash method) from Payment rows.
 *
 * Recognition must never break the payment flow: every check is a plain PHP
 * lookup before persist, and a skipped outcome leaves the payment untouched.
 * The unique (source_type, source_id) index stays as the race-condition safety net.
 */
class IncomeRecognitionService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly IncomeRecordRepository $incomeRecordRepository,
		private readonly NbuExchangeRateProvider $nbuExchangeRateProvider,
		private readonly IncomeRecordHistoryRecorder $incomeRecordHistoryRecorder,
	)
	{
	}

	public function recognizePayment(Payment $payment, ?User $actor = null, bool $persist = true): RecognitionOutcome
	{
		if ($payment->getId() === null) {
			return RecognitionOutcome::skipped(RecognitionOutcome::REASON_UNSAVED_PAYMENT);
		}

		$classification = $this->classify($payment);
		if (!$classification instanceof IncomeClassificationEnum) {
			return RecognitionOutcome::skipped(RecognitionOutcome::REASON_NOT_INCOME_RELEVANT);
		}

		$legalEntity = $payment->getStore()?->getLegalEntity();
		if (!$legalEntity instanceof LegalEntity) {
			return RecognitionOutcome::skipped(RecognitionOutcome::REASON_NO_LEGAL_ENTITY);
		}

		if ($this->findExistingRecord($payment) instanceof IncomeRecord) {
			return RecognitionOutcome::skipped(RecognitionOutcome::REASON_ALREADY_RECOGNIZED);
		}

		$currency = $payment->getCurrency();
		$recognizedAt = $payment->getPaidAt()->setTime(0, 0);
		$rate = $currency instanceof Currency
			? $this->nbuExchangeRateProvider->getRate($currency, $recognizedAt)
			: null;
		if ($rate === null) {
			return RecognitionOutcome::skipped(RecognitionOutcome::REASON_MISSING_NBU_RATE);
		}

		$record = (new IncomeRecord())
			->setLegalEntity($legalEntity)
			->setStore($payment->getStore())
			->setRecognizedAt($recognizedAt)
			->setAmount((string) $payment->getAmount())
			->setCurrency($currency)
			->setNbuExchangeRate($rate)
			->setAmountUah(number_format((float) $payment->getAmount() * (float) $rate, 4, '.', ''))
			->setSourceType(IncomeSourceTypeEnum::PAYMENT)
			->setSourceId($payment->getId())
			->setPayment($payment)
			->setClassification($classification)
			->setCounterparty($this->counterparty($payment))
			->setCreatedBy($actor)
			->setUpdatedBy($actor);

		if ($classification === IncomeClassificationEnum::REFUND) {
			$original = $payment->getReversesPayment();
			if ($original instanceof Payment) {
				$record->setRefundOfIncomeRecord($this->findExistingRecord($original));
			}
		}

		if ($persist) {
			$this->entityManager->persist($record);
			$this->incomeRecordHistoryRecorder->recordRecognized($record, $actor);
		}

		return RecognitionOutcome::created($record);
	}

	/**
	 * Cash-method classification rules. Null means the payment is not relevant
	 * for income accounting (regular outgoing payments for purchases).
	 */
	private function classify(Payment $payment): ?IncomeClassificationEnum
	{
		$isReversal = $payment->getReversesPayment() instanceof Payment;

		if ($payment->getDirection() === PaymentDirectionEnum::INCOMING) {
			if ($isReversal || $payment->getPurchase() !== null) {
				// Reversal of an outgoing payment (money returned by a supplier)
				// or a direct incoming payment on a purchase: own funds, not income.
				return IncomeClassificationEnum::NON_INCOME_OTHER;
			}

			return IncomeClassificationEnum::INCOME;
		}

		if ($isReversal) {
			// Reversal of an incoming payment: refund that reduces income
			// in the period when the money was returned.
			return IncomeClassificationEnum::REFUND;
		}

		return null;
	}

	private function findExistingRecord(Payment $payment): ?IncomeRecord
	{
		return $this->incomeRecordRepository->findOneBy([
			'sourceType' => IncomeSourceTypeEnum::PAYMENT,
			'sourceId' => $payment->getId(),
		]);
	}

	private function counterparty(Payment $payment): ?string
	{
		$customer = $payment->getOrder()?->getCustomer();
		if ($customer !== null) {
			return (string) $customer;
		}

		$supplier = $payment->getPurchase()?->getSupplier();

		return $supplier !== null ? (string) $supplier : null;
	}
}

<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\PaymentHistory;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

class PaymentHistoryRecorder
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function recordPaymentCreated(Payment $payment, ?User $actor): void
	{
		$this->record(
			payment: $payment,
			actor: $actor,
			eventKey: 'payment.recorded',
			title: 'Payment recorded',
			description: $this->paymentDescription($payment),
			payload: [],
		);
	}

	public function recordPaymentReversed(Payment $payment, Payment $reversal, ?User $actor): void
	{
		$this->record(
			payment: $reversal,
			actor: $actor,
			eventKey: 'payment.reversed',
			title: 'Payment reversed',
			description: $this->paymentDescription($reversal),
			payload: [
				'original_payment_id' => $payment->getId(),
				'reason' => $reversal->getComment(),
			],
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function record(Payment $payment, ?User $actor, string $eventKey, string $title, string $description, array $payload): void
	{
		$store = $payment->getStore();
		if (!$store instanceof Store) {
			throw new LogicException('Cannot record payment history without store.');
		}

		$currency = $payment->getCurrency();
		if ($currency === null) {
			throw new LogicException('Cannot record payment history without currency.');
		}

		$order = $payment->getOrder();
		$purchase = $payment->getPurchase();
		$targetCount = (int) ($order instanceof Order) + (int) ($purchase instanceof Purchase);
		if ($targetCount !== 1) {
			throw new LogicException('Cannot record payment history without exactly one document.');
		}

		$documentId = $order instanceof Order ? $order->getId() : $purchase?->getId();
		if (!is_int($documentId)) {
			throw new LogicException('Cannot record payment history for unsaved document.');
		}

		$history = (new PaymentHistory())
			->setEventKey($eventKey)
			->setDocumentType($order instanceof Order ? 'order' : 'purchase')
			->setDocumentId($documentId)
			->setTitle($title)
			->setDescription($description)
			->setAmount((string) $payment->getAmount())
			->setAmountBase((string) $payment->getAmountBase())
			->setCurrencyCode($currency->getCode())
			->setDirection($payment->getDirection())
			->setType($payment->getType())
			->setExternalReference($payment->getExternalReference())
			->setPayload($payload !== [] ? $payload : null)
			->setOccurredAt($payment->getPaidAt())
			->setActor($actor)
			->setActorNameSnapshot($this->actorName($actor))
			->setStore($store)
			->setOrder($order)
			->setPurchase($purchase)
			->setPayment($payment);

		$this->entityManager->persist($history);
	}

	private function paymentDescription(Payment $payment): string
	{
		$currency = $payment->getCurrency();

		return sprintf(
			'%s %s %s payment: %s %s (%s base).',
			ucfirst($payment->getDirection()->value),
			$payment->getType()->value,
			$payment->getType()->value === 'refund' ? 'correction' : 'document',
			$payment->getAmount(),
			$currency?->getCode() ?? '',
			$payment->getAmountBase(),
		);
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

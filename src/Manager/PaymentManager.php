<?php

namespace App\Manager;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Entity\User\User;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use App\Repository\PaymentRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use App\Service\ExchangeRateResolver;
use App\Service\Payment\OrderPaymentEligibilityService;
use App\Service\Payment\PaymentAmountLimitService;
use App\Service\Payment\PaymentHistoryRecorder;
use App\Service\Payment\PaymentRecalculationService;
use App\Service\Tax\IncomeRecognitionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class PaymentManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ExchangeRateResolver $exchangeRateResolver,
		private readonly PaymentRecalculationService $paymentRecalculationService,
		private readonly PaymentHistoryRecorder $paymentHistoryRecorder,
		private readonly PaymentAmountLimitService $paymentAmountLimitService,
		private readonly OrderPaymentEligibilityService $orderPaymentEligibilityService,
		private readonly UserManager $userManager,
		private readonly ConcurrencyGuard $concurrencyGuard,
		private readonly IncomeRecognitionService $incomeRecognitionService,
	)
	{
	}

	public function createForStore(Store $store): Payment
	{
		return (new Payment())
			->setStore($store)
			->setCurrency($store->getBaseCurrency());
	}

	public function prefillFromOrder(Payment $payment, Order $order): void
	{
		$this->prefillIncomingFromOrder($payment, $order);
	}

	public function prefillIncomingFromOrder(Payment $payment, Order $order): void
	{
		$this->prefillFromDocument($payment, $order, PaymentDirectionEnum::INCOMING);
	}

	public function prefillRefundFromOrder(Payment $payment, Order $order): void
	{
		$payment
			->setCurrency($order->getCurrency())
			->setDirection(PaymentDirectionEnum::OUTGOING)
			->setType(PaymentTypeEnum::CASH)
			->setAmount($this->documentAmountFromBase($order, $order->getPaidAmountBase()))
			->setOrder($order);
	}

	public function prefillFromPurchase(Payment $payment, Purchase $purchase): void
	{
		$this->prefillFromDocument($payment, $purchase, PaymentDirectionEnum::OUTGOING);
	}

	/**
	 * @return array{direction: string, currency: string|null, amount: string}
	 */
	public function defaultsForOrder(Order $order): array
	{
		return $this->defaultsForDocument($order, PaymentDirectionEnum::INCOMING);
	}

	/**
	 * @return array{direction: string, currency: string|null, amount: string}
	 */
	public function defaultsForPurchase(Purchase $purchase): array
	{
		return $this->defaultsForDocument($purchase, PaymentDirectionEnum::OUTGOING);
	}

	public function savePayment(Payment $payment, ?Order $previousOrder = null, ?Purchase $previousPurchase = null): void
	{
		$this->entityManager->wrapInTransaction(function () use ($payment, $previousOrder, $previousPurchase): void {
			if ($payment->getId() !== null) {
				throw new RuntimeException('Recorded payments cannot be edited. Use reversal correction flow.');
			}

			$this->assertValidTarget($payment);
			$this->concurrencyGuard->lockAll($this->documentsToRecalculate($payment, $previousOrder, $previousPurchase));
			$this->assertOrderPaymentAllowed($payment);
			$this->preparePayment($payment);
			$this->paymentAmountLimitService->assertWithinLimits($payment, true);

			$actor = $this->currentActor();
			if ($payment->getId() === null) {
				$payment->setCreatedBy($actor);
			}

			$payment
				->setUpdatedBy($actor)
				->setUpdatedAt(new DateTimeImmutable());

			$this->syncOwningCollections($payment, $previousOrder, $previousPurchase);

			$this->entityManager->persist($payment);
			foreach ($this->documentsToRecalculate($payment, $previousOrder, $previousPurchase) as $document) {
				$this->paymentRecalculationService->recalculate($document);
				$this->entityManager->persist($document);
			}
			$this->paymentHistoryRecorder->recordPaymentCreated($payment, $actor);

			$this->entityManager->flush();

			// Tax income projection runs after the flush (payment id is required)
			// and never blocks the payment: skipped outcomes are silent.
			if ($this->incomeRecognitionService->recognizePayment($payment, $actor)->isCreated()) {
				$this->entityManager->flush();
			}
		});
	}

	public function reversePayment(Payment $payment, string $reason): Payment
	{
		return $this->entityManager->wrapInTransaction(function () use ($payment, $reason): Payment {
			$this->concurrencyGuard->lock($payment);
			$reason = trim($reason);

			if ($reason === '') {
				throw new RuntimeException('Reversal reason is required.');
			}

			if ($payment->getId() === null) {
				throw new RuntimeException('Only recorded payments can be reversed.');
			}

			if ($payment->getReversesPayment() instanceof Payment) {
				throw new RuntimeException('Payment correction cannot be reversed by this flow.');
			}

			if ($payment->getReversedByPayment() instanceof Payment) {
				throw new RuntimeException('Payment was already reversed.');
			}

			$this->concurrencyGuard->lockAll($this->documentsToRecalculate($payment, null, null));

			$actor = $this->currentActor();
			$reversal = (new Payment())
				->setStore($payment->getStore())
				->setDirection($this->oppositeDirection($payment->getDirection()))
				->setType($payment->getType())
				->setAmount((string) $payment->getAmount())
				->setAmountBase((string) $payment->getAmountBase())
				->setPaidAt(new DateTimeImmutable())
				->setCurrency($payment->getCurrency())
				->setExchangeRateToBase((string) $payment->getExchangeRateToBase())
				->setOrder($payment->getOrder())
				->setPurchase($payment->getPurchase())
				->setComment($reason)
				->setCreatedBy($actor)
				->setUpdatedBy($actor)
				->setReversesPayment($payment);

			$payment->setReversedByPayment($reversal);

			$this->entityManager->persist($reversal);
			foreach ($this->documentsToRecalculate($reversal, null, null) as $document) {
				$document->addPayment($reversal);
				$this->paymentRecalculationService->recalculate($document);
				$this->entityManager->persist($document);
			}
			$this->paymentHistoryRecorder->recordPaymentReversed($payment, $reversal, $actor);

			$this->entityManager->flush();

			// Tax income projection: the reversal becomes a refund record that
			// reduces income in the reversal period. Never blocks the payment flow.
			if ($this->incomeRecognitionService->recognizePayment($reversal, $actor)->isCreated()) {
				$this->entityManager->flush();
			}

			return $reversal;
		});
	}

	public function getRepository(): PaymentRepository
	{
		return $this->entityManager->getRepository(Payment::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function assertValidTarget(Payment $payment): void
	{
		$targetCount = (int) ($payment->getOrder() instanceof Order)
			+ (int) ($payment->getPurchase() instanceof Purchase);

		if ($targetCount !== 1) {
			throw new RuntimeException('Select exactly one payment document.');
		}

		$store = $payment->getStore();
		if (!$store instanceof Store) {
			throw new RuntimeException('Payment store is required.');
		}

		if ($payment->getOrder() instanceof Order && $payment->getOrder()->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Selected order does not belong to current store.');
		}

		if ($payment->getPurchase() instanceof Purchase && $payment->getPurchase()->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Selected purchase does not belong to current store.');
		}
	}

	private function assertOrderPaymentAllowed(Payment $payment): void
	{
		$violation = $this->orderPaymentEligibilityService->violation($payment);

		if ($violation !== null) {
			throw new RuntimeException($violation);
		}
	}

	private function preparePayment(Payment $payment): void
	{
		$currency = $payment->getCurrency();
		$store = $payment->getStore();
		$baseCurrency = $store?->getBaseCurrency();

		if (!$currency || !$store || !$baseCurrency) {
			return;
		}

		$exchangeRateToBase = $this->exchangeRateResolver->resolve($currency, $baseCurrency, $store, $payment->getPaidAt());

		$payment
			->setExchangeRateToBase($exchangeRateToBase)
			->setAmountBase(number_format((float) $payment->getAmount() * (float) $exchangeRateToBase, 4, '.', ''));
	}

	private function syncOwningCollections(Payment $payment, ?Order $previousOrder, ?Purchase $previousPurchase): void
	{
		if ($previousOrder instanceof Order && $previousOrder !== $payment->getOrder()) {
			$previousOrder->removePayment($payment);
		}

		if ($previousPurchase instanceof Purchase && $previousPurchase !== $payment->getPurchase()) {
			$previousPurchase->removePayment($payment);
		}

		if ($payment->getOrder() instanceof Order) {
			$payment->getOrder()->addPayment($payment);
		}

		if ($payment->getPurchase() instanceof Purchase) {
			$payment->getPurchase()->addPayment($payment);
		}
	}

	/**
	 * @return array<int, Order|Purchase>
	 */
	private function documentsToRecalculate(Payment $payment, ?Order $previousOrder, ?Purchase $previousPurchase): array
	{
		$documents = [];

		foreach ([$previousOrder, $previousPurchase, $payment->getOrder(), $payment->getPurchase()] as $document) {
			if (!$document instanceof Order && !$document instanceof Purchase) {
				continue;
			}

			$documents[sprintf('%s:%d', $document::class, $document->getId() ?? spl_object_id($document))] = $document;
		}

		return array_values($documents);
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
	}

	private function prefillFromDocument(Payment $payment, Order|Purchase $document, PaymentDirectionEnum $direction): void
	{
		$payment
			->setCurrency($document->getCurrency())
			->setDirection($direction)
			->setAmount($this->remainingDocumentAmount($document));

		if ($document instanceof Order) {
			$payment->setOrder($document);

			return;
		}

		$payment->setPurchase($document);
	}

	/**
	 * @return array{direction: string, currency: string|null, amount: string}
	 */
	private function defaultsForDocument(Order|Purchase $document, PaymentDirectionEnum $direction): array
	{
		return [
			'direction' => $direction->value,
			'currency' => $document->getCurrency()?->getCode(),
			'amount' => $this->remainingDocumentAmount($document),
		];
	}

	private function remainingDocumentAmount(Order|Purchase $document): string
	{
		$remainingAmountBase = max(
			0,
			$this->numberValue($document->getTotalAmountBase()) - $this->numberValue($document->getPaidAmountBase()),
		);

		return $this->documentAmountFromBase($document, (string) $remainingAmountBase);
	}

	private function documentAmountFromBase(Order|Purchase $document, mixed $amountBase): string
	{
		$exchangeRateToBase = $this->numberValue($document->getExchangeRateToBase());
		if ($exchangeRateToBase <= 0) {
			return '0.0000';
		}

		return $this->formatMoney(max(0, $this->numberValue($amountBase)) / $exchangeRateToBase);
	}

	private function oppositeDirection(PaymentDirectionEnum $direction): PaymentDirectionEnum
	{
		return $direction === PaymentDirectionEnum::INCOMING
			? PaymentDirectionEnum::OUTGOING
			: PaymentDirectionEnum::INCOMING;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

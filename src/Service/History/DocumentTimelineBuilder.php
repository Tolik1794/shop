<?php

namespace App\Service\History;

use App\Dto\DocumentTimelineEntry;
use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\PaymentHistory;
use App\Entity\Purchase;
use App\Entity\StatusHistory;
use App\Entity\StatusHistoryEntityType;
use App\Entity\Store;
use App\Repository\OrderHistoryRepository;
use App\Repository\PaymentHistoryRepository;
use App\Repository\StatusHistoryRepository;

class DocumentTimelineBuilder
{
	public function __construct(
		private readonly OrderHistoryRepository $orderHistoryRepository,
		private readonly StatusHistoryRepository $statusHistoryRepository,
		private readonly PaymentHistoryRepository $paymentHistoryRepository,
	)
	{
	}

	/**
	 * @return DocumentTimelineEntry[]
	 */
	public function forOrder(Order $order): array
	{
		$entries = [
			...array_map($this->fromOrderHistory(...), $this->orderHistoryRepository->findTimelineByOrder($order)),
			...array_map($this->fromPaymentHistory(...), $this->paymentHistoryRepository->findTimelineByOrder($order)),
		];

		return $this->sort($entries);
	}

	/**
	 * @return DocumentTimelineEntry[]
	 */
	public function forPurchase(Store $store, Purchase $purchase): array
	{
		$purchaseId = $purchase->getId();
		if (!is_int($purchaseId)) {
			return [];
		}

		$entries = [
			...array_map(
				$this->fromStatusHistory(...),
				$this->statusHistoryRepository->findTimelineFor($store, StatusHistoryEntityType::PURCHASE, $purchaseId),
			),
			...array_map($this->fromPaymentHistory(...), $this->paymentHistoryRepository->findTimelineByPurchase($purchase)),
		];

		return $this->sort($entries);
	}

	private function fromOrderHistory(OrderHistory $history): DocumentTimelineEntry
	{
		return new DocumentTimelineEntry(
			occurredAt: $history->getOccurredAt(),
			title: $history->getTitle(),
			source: $history->getSource()->value,
			eventKey: $history->getEventKey(),
			actorName: $history->getActorNameSnapshot(),
			description: $history->getDescription(),
			changes: $history->getChanges(),
			sortId: $history->getId() ?? 0,
		);
	}

	private function fromStatusHistory(StatusHistory $history): DocumentTimelineEntry
	{
		return new DocumentTimelineEntry(
			occurredAt: $history->getChangedAt(),
			title: sprintf('%s -> %s', $history->getOldStatus() ?: 'new', $history->getNewStatus()),
			source: $history->getEntityType()->value,
			eventKey: 'status.changed',
			actorName: $history->getChangedBy()?->getEmail(),
			description: $history->getComment(),
			sortId: $history->getId() ?? 0,
		);
	}

	private function fromPaymentHistory(PaymentHistory $history): DocumentTimelineEntry
	{
		$changes = [
			'amount' => ['from' => null, 'to' => sprintf('%s %s', $history->getAmount(), $history->getCurrencyCode())],
			'amountBase' => ['from' => null, 'to' => $history->getAmountBase()],
		];

		if ($history->getExternalReference() !== null) {
			$changes['externalReference'] = ['from' => null, 'to' => $history->getExternalReference()];
		}

		return new DocumentTimelineEntry(
			occurredAt: $history->getOccurredAt(),
			title: $history->getTitle(),
			source: 'payment',
			eventKey: $history->getEventKey(),
			actorName: $history->getActorNameSnapshot(),
			description: $history->getDescription(),
			changes: $changes,
			sortId: $history->getId() ?? 0,
		);
	}

	/**
	 * @param DocumentTimelineEntry[] $entries
	 *
	 * @return DocumentTimelineEntry[]
	 */
	private function sort(array $entries): array
	{
		usort($entries, static function (DocumentTimelineEntry $left, DocumentTimelineEntry $right): int {
			$dateComparison = $right->getOccurredAt() <=> $left->getOccurredAt();

			return $dateComparison !== 0
				? $dateComparison
				: ($right->getSortId() <=> $left->getSortId());
		});

		return $entries;
	}
}

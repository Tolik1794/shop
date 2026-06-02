<?php

namespace App\Service\Customer;

use App\Entity\Customer;
use App\Repository\InventoryDocumentRepository;
use App\Repository\OrderCommentRepository;
use App\Repository\OrderRepository;

/**
 * Assembles a compact, read-only "quick customer history" snapshot for display in the
 * customer card and the order customer-history tab.
 */
class CustomerHistoryProvider
{
	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderCommentRepository $orderCommentRepository,
		private readonly InventoryDocumentRepository $inventoryDocumentRepository,
	)
	{
	}

	/**
	 * @return array{
	 *     labels: iterable,
	 *     recent_orders: array,
	 *     important_comments: array,
	 *     returns: array
	 * }
	 */
	public function forCustomer(Customer $customer, int $limit = 10): array
	{
		return [
			'labels' => $customer->getLabels(),
			'recent_orders' => $this->orderRepository->findRecentByCustomer($customer, $limit),
			'important_comments' => $this->orderCommentRepository->findImportantByCustomer($customer, $limit),
			'returns' => $this->inventoryDocumentRepository->findCustomerReturnsByCustomer($customer, $limit),
		];
	}
}

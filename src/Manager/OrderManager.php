<?php

namespace App\Manager;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\User\User;
use App\Repository\OrderRepository;
use App\Service\ExchangeRateResolver;
use App\Service\Order\OrderEntryPricingService;
use App\Service\Order\OrderEntrySnapshotter;
use App\Service\OrderCalculator;
use App\Workflow\History\OrderHistoryChangeSetBuilder;
use App\Workflow\History\OrderHistoryRecorder;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionContext;
use Doctrine\ORM\EntityManagerInterface;

class OrderManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ExchangeRateResolver $exchangeRateResolver,
		private readonly OrderEntrySnapshotter $orderEntrySnapshotter,
		private readonly OrderEntryPricingService $orderEntryPricingService,
		private readonly OrderCalculator $orderCalculator,
		private readonly StatusTransitionService $statusTransitionService,
		private readonly OrderHistoryChangeSetBuilder $orderHistoryChangeSetBuilder,
		private readonly OrderHistoryRecorder $orderHistoryRecorder,
		private readonly UserManager $userManager,
	)
	{
	}

	public function createDraft(Store $store): Order
	{
		return (new Order())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setNumber($this->getRepository()->getNextNumber($store));
	}

	public function saveOrder(Order $order, iterable $removedEntries = []): void
	{
		$isNew = $order->getId() === null;
		$actor = $this->currentActor();
		$this->prepareOrder($order);
		foreach ($removedEntries as $removedEntry) {
			if ($removedEntry instanceof OrderEntry) {
				$this->entityManager->remove($removedEntry);
			}
		}
		foreach ($order->getOrderEntries() as $orderEntry) {
			$orderEntry->setOrder($order);
			$this->prepareEntry($orderEntry);
			$this->entityManager->persist($orderEntry);
		}
		$this->recalculate($order);

		if ($isNew) {
			$this->orderHistoryRecorder->recordCreated($order, $actor);
		} else {
			$orderChanges = $this->orderHistoryChangeSetBuilder->buildOrderChanges($order);
			$this->orderHistoryRecorder->recordUpdated($order, $orderChanges, $actor);

			if (array_key_exists('customer', $orderChanges)) {
				$this->orderHistoryRecorder->recordCustomerChanged($order, [
					'customer' => $orderChanges['customer'],
				], $actor);
			}
		}

		foreach ($order->getOrderEntries() as $orderEntry) {
			if ($orderEntry->getId() === null) {
				$this->orderHistoryRecorder->recordEntryAdded($order, $this->orderHistoryChangeSetBuilder->buildEntryPayload($orderEntry), $actor);

				continue;
			}

			$this->orderHistoryRecorder->recordEntryUpdated(
				$order,
				$this->orderHistoryChangeSetBuilder->buildEntryChanges($orderEntry),
				$actor,
				$orderEntry->getId(),
			);
		}

		foreach ($removedEntries as $removedEntry) {
			if ($removedEntry instanceof OrderEntry) {
				$this->orderHistoryRecorder->recordEntryRemoved(
					$order,
					$this->orderHistoryChangeSetBuilder->buildEntryPayload($removedEntry),
					$actor,
					$removedEntry->getId(),
				);
			}
		}

		$this->save($order);
	}

	public function confirm(Order $order): void
	{
		$this->statusTransitionService->apply($order, 'confirm', $this->transitionContext());
		$this->saveOrder($order);
	}

	public function cancel(Order $order): void
	{
		if ($order->getStatus() === OrderStatus::CANCELED) {
			return;
		}

		$this->statusTransitionService->apply($order, 'cancel', $this->transitionContext());
		$this->saveOrder($order);
	}

	public function returnToDraft(Order $order): void
	{
		$this->statusTransitionService->apply($order, 'return_to_draft', $this->transitionContext());
		$this->saveOrder($order);
	}

	public function recalculate(Order $order): void
	{
		foreach ($order->getOrderEntries() as $orderEntry) {
			$this->prepareEntry($orderEntry);
		}

		$this->orderCalculator->apply($order);
	}

	public function getRepository(): OrderRepository
	{
		return $this->entityManager->getRepository(Order::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function prepareOrder(Order $order): void
	{
		$currency = $order->getCurrency();
		$store = $order->getStore();
		$baseCurrency = $store?->getBaseCurrency();

		if (!$currency || !$store || !$baseCurrency) {
			return;
		}

		$order->setExchangeRateToBase($this->exchangeRateResolver->resolve($currency, $baseCurrency, $store));
	}

	private function prepareEntry(OrderEntry $orderEntry): void
	{
		$product = $orderEntry->getProduct();
		$order = $orderEntry->getOrder();

		if (!$product instanceof Product || !$order instanceof Order) {
			return;
		}

		$this->orderEntrySnapshotter->snapshot($orderEntry);
		$this->orderEntryPricingService->initializeUnitPrice($orderEntry, $order);
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
	}

	private function transitionContext(): TransitionContext
	{
		$actor = $this->currentActor();

		return $actor instanceof User
			? TransitionContext::manual($actor)
			: TransitionContext::system();
	}

}

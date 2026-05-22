<?php

namespace App\Manager;

use App\Entity\Product;
use App\Entity\Purchase;
use App\Entity\PurchaseEntry;
use App\Entity\PurchaseStatus;
use App\Entity\Store;
use App\Entity\User\User;
use App\Repository\PurchaseRepository;
use App\Service\ExchangeRateResolver;
use App\Service\Purchase\PurchaseEntrySnapshotter;
use App\Service\PurchaseCalculator;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionContext;
use Doctrine\ORM\EntityManagerInterface;

class PurchaseManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ExchangeRateResolver $exchangeRateResolver,
		private readonly PurchaseEntrySnapshotter $purchaseEntrySnapshotter,
		private readonly PurchaseCalculator $purchaseCalculator,
		private readonly StatusTransitionService $statusTransitionService,
		private readonly UserManager $userManager,
	)
	{
	}

	public function createDraft(Store $store): Purchase
	{
		return (new Purchase())
			->setStore($store)
			->setCurrency($store->getBaseCurrency())
			->setExchangeRateToBase('1.00000000')
			->setNumber($this->getRepository()->getNextNumber($store));
	}

	public function savePurchase(Purchase $purchase, iterable $removedEntries = []): void
	{
		$this->preparePurchase($purchase);

		foreach ($removedEntries as $removedEntry) {
			if ($removedEntry instanceof PurchaseEntry) {
				$this->entityManager->remove($removedEntry);
			}
		}

		foreach ($purchase->getPurchaseEntries() as $purchaseEntry) {
			$purchaseEntry->setPurchase($purchase);
			$this->prepareEntry($purchaseEntry);
			$this->entityManager->persist($purchaseEntry);
		}

		$this->purchaseCalculator->apply($purchase);
		$this->save($purchase);
	}

	public function order(Purchase $purchase): void
	{
		$this->statusTransitionService->apply($purchase, 'order', $this->transitionContext());
		$this->savePurchase($purchase);
	}

	public function returnToDraft(Purchase $purchase): void
	{
		$this->statusTransitionService->apply($purchase, 'return_to_draft', $this->transitionContext());
		$this->savePurchase($purchase);
	}

	public function cancel(Purchase $purchase): void
	{
		if ($purchase->getStatus() === PurchaseStatus::CANCELED) {
			return;
		}

		$this->statusTransitionService->apply($purchase, 'cancel', $this->transitionContext());
		$this->savePurchase($purchase);
	}

	public function complete(Purchase $purchase): void
	{
		$this->statusTransitionService->apply($purchase, 'complete', $this->transitionContext());
		$this->savePurchase($purchase);
	}

	public function getRepository(): PurchaseRepository
	{
		return $this->entityManager->getRepository(Purchase::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function preparePurchase(Purchase $purchase): void
	{
		$currency = $purchase->getCurrency();
		$store = $purchase->getStore();
		$baseCurrency = $store?->getBaseCurrency();

		if (!$currency || !$store || !$baseCurrency) {
			return;
		}

		$purchase->setExchangeRateToBase($this->exchangeRateResolver->resolve($currency, $baseCurrency, $store));
	}

	private function prepareEntry(PurchaseEntry $purchaseEntry): void
	{
		if (!$purchaseEntry->getProduct() instanceof Product) {
			return;
		}

		$this->purchaseEntrySnapshotter->snapshot($purchaseEntry);
	}

	private function transitionContext(): TransitionContext
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User
			? TransitionContext::manual($user)
			: TransitionContext::system();
	}
}

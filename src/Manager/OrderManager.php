<?php

namespace App\Manager;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Entity\Store;
use App\Repository\OrderRepository;
use App\Repository\ProductPriceRepository;
use App\Service\ExchangeRateResolver;
use App\Service\OrderCalculator;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionContext;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class OrderManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ProductPriceRepository $productPriceRepository,
		private readonly ExchangeRateResolver $exchangeRateResolver,
		private readonly OrderCalculator $orderCalculator,
		private readonly StatusTransitionService $statusTransitionService,
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
		$this->save($order);
	}

	public function confirm(Order $order): void
	{
		$this->statusTransitionService->apply($order, 'confirm', TransitionContext::system());
		$this->saveOrder($order);
	}

	public function cancel(Order $order): void
	{
		if ($order->getStatus() === OrderStatus::CANCELED) {
			return;
		}

		$this->statusTransitionService->apply($order, 'cancel', TransitionContext::system());
		$this->saveOrder($order);
	}

	public function returnToDraft(Order $order): void
	{
		$this->statusTransitionService->apply($order, 'return_to_draft', TransitionContext::system());
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

		$orderEntry
			->setProductNameSnapshot((string) $product->getName())
			->setProductCodeSnapshot((string) $product->getCode())
			->setUnitCodeSnapshot((string) $product->getUnit()?->getCode())
			->setUnitNameSnapshot((string) $product->getUnit()?->getName());

		if ((float) $orderEntry->getUnitPrice() <= 0) {
			$this->setEntryPriceFromProduct($orderEntry, $product, $order);
		}

	}

	private function setEntryPriceFromProduct(OrderEntry $orderEntry, Product $product, Order $order): void
	{
		$productPrice = $this->productPriceRepository->findCurrentRegularPrice($product, $order->getCurrency());

		if ($productPrice instanceof ProductPrice) {
			$orderEntry->setUnitPrice($productPrice->getPrice());

			return;
		}

		if ($product->getBaseSalePrice() === null) {
			throw new RuntimeException(sprintf('Product "%s" has no sale price.', $product->getName()));
		}

		$exchangeRateToBase = (float) $order->getExchangeRateToBase();
		$unitPrice = $exchangeRateToBase > 0
			? (float) $product->getBaseSalePrice() / $exchangeRateToBase
			: (float) $product->getBaseSalePrice();

		$orderEntry->setUnitPrice($this->formatMoney($unitPrice));
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}

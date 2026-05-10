<?php

namespace App\Manager;

use App\Entity\ProductPrice;
use App\Enum\ProductPriceTypeEnum;
use App\Repository\ProductPriceRepository;
use App\Service\ExchangeRateResolver;
use Doctrine\ORM\EntityManagerInterface;

class ProductPriceManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ExchangeRateResolver $exchangeRateResolver,
	)
	{
	}

	public function getRepository(): ProductPriceRepository
	{
		return $this->entityManager->getRepository(ProductPrice::class);
	}

	public function saveWithTimeline(ProductPrice $productPrice): void
	{
		$this->calculatePriceBase($productPrice);

		if ($productPrice->getId() === null) {
			$productPrice->setValidTo(null);

			$previousProductPrice = $this->getRepository()->findOpenPriceBefore($productPrice);

			if ($previousProductPrice instanceof ProductPrice) {
				$previousProductPrice->setValidTo($productPrice->getValidFrom());
				$this->entityManager->persist($previousProductPrice);
			}
		}

		$this->syncProductBaseSalePrice($productPrice);
		$this->entityManager->persist($productPrice);
		$this->entityManager->flush();
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function calculatePriceBase(ProductPrice $productPrice): void
	{
		$store = $productPrice->getStore();
		$currency = $productPrice->getCurrency();
		$baseCurrency = $store?->getBaseCurrency();

		if (!$store || !$currency || !$baseCurrency || $productPrice->getPrice() === null) {
			return;
		}

		$rate = $this->exchangeRateResolver->resolve($currency, $baseCurrency, $store, $productPrice->getValidFrom());

		$productPrice->setPriceBase(number_format((float)$productPrice->getPrice() * (float)$rate, 4, '.', ''));
	}

	private function syncProductBaseSalePrice(ProductPrice $productPrice): void
	{
		$product = $productPrice->getProduct();
		$store = $productPrice->getStore();
		$currency = $productPrice->getCurrency();

		if (!$product || !$store || !$currency || $productPrice->getType() !== ProductPriceTypeEnum::REGULAR) {
			return;
		}

		if ($productPrice->getValidTo() !== null) {
			return;
		}

		$product->setBaseSalePrice($productPrice->getPriceBase());
		$this->entityManager->persist($product);
	}
}

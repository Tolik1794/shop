<?php

namespace App\Manager;

use App\Entity\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use Doctrine\ORM\EntityManagerInterface;

class ExchangeRateManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
	)
	{
	}

	public function getRepository(): ExchangeRateRepository
	{
		return $this->entityManager->getRepository(ExchangeRate::class);
	}

	public function saveWithTimeline(ExchangeRate $exchangeRate): void
	{
		if ($exchangeRate->getId() === null) {
			$exchangeRate->setValidTo(null);

			$previousExchangeRate = $this->getRepository()->findOpenRateBefore($exchangeRate);

			if ($previousExchangeRate instanceof ExchangeRate) {
				$previousExchangeRate->setValidTo($exchangeRate->getValidFrom());
				$this->entityManager->persist($previousExchangeRate);
			}
		}

		$this->entityManager->persist($exchangeRate);
		$this->entityManager->flush();
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}

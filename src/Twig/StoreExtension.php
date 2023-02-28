<?php

namespace App\Twig;

use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Manager\StoreManager;
use App\Repository\StoreRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class StoreExtension extends AbstractExtension
{
	public function __construct(
		private readonly RequestStack $requestStack,
		private readonly StoreManager $storeManager,
	)
	{
	}

	public function getFunctions()
	{
		return [
			new TwigFunction('store', [$this, 'getStore']),
		];
	}

	public function getStore(): ?Store
	{
		return ($storeId = $this->requestStack->getCurrentRequest()->get('store_id'))
			? $this->storeManager->getRepository()->find($storeId)
			: null;
	}
}
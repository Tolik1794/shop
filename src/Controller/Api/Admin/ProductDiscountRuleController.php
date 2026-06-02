<?php

namespace App\Controller\Api\Admin;

use App\Entity\Currency;
use App\Entity\Store;
use App\Repository\ProductRepository;
use App\Service\Discount\DiscountCostBasisCalculator;
use App\Service\Pricing\CatalogPriceResolver;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/store/{store_id}/discount', name: 'app_api_admin_product_discount_'), IsGranted('product_discount.view')]
class ProductDiscountRuleController extends AbstractController
{
	public function __construct(
		private readonly ProductRepository $productRepository,
		private readonly CatalogPriceResolver $catalogPriceResolver,
		private readonly DiscountCostBasisCalculator $discountCostBasisCalculator,
	)
	{
	}

	#[Route('/product-search', name: 'product_search', methods: ['GET'])]
	public function productSearch(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): JsonResponse
	{
		$search = trim((string) $request->query->get('q', ''));
		if (mb_strlen($search) < 3) {
			return $this->json(['results' => []]);
		}

		$currency = $store->getBaseCurrency();
		if (!$currency instanceof Currency) {
			throw new RuntimeException('Store has no base currency.');
		}

		$results = [];
		foreach ($this->productRepository->findChoicesByStoreAndSearch($store, $search) as $product) {
			$price = $this->catalogPriceResolver->tryResolve($product, $store, $currency)?->getAmount();
			$cost = $this->discountCostBasisCalculator->forProduct($product, $store)->getEffectiveUnitCostBase();
			$results[] = [
				'id' => $product->getId(),
				'text' => sprintf('%s (%s)', $product->getName(), $product->getCode()),
				'price' => $price,
				'cost' => $cost,
				'currency' => $currency->getCode(),
			];
		}

		return $this->json(['results' => $results]);
	}
}

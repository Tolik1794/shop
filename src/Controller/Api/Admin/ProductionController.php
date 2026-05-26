<?php

namespace App\Controller\Api\Admin;

use App\Entity\Store;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/store/{store_id}/production', name: 'app_api_admin_production_'), IsGranted('production_order.view')]
class ProductionController extends AbstractController
{
	public function __construct(private readonly ProductRepository $productRepository)
	{
	}

	#[Route('/material-search', name: 'search_material_products', methods: ['GET'])]
	public function searchMaterialProducts(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): JsonResponse
	{
		$search = trim((string) $request->query->get('q', ''));

		if (mb_strlen($search) < 3) {
			return $this->json(['results' => []]);
		}

		$results = [];
		foreach ($this->productRepository->findMaterialChoicesByStoreAndSearch($store, $search) as $product) {
			$results[] = [
				'id' => $product->getId(),
				'text' => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			];
		}

		return $this->json(['results' => $results]);
	}
}

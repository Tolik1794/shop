<?php

namespace App\Controller\Api\Admin;

use App\Entity\Order;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Manager\PaymentManager;
use App\Repository\OrderRepository;
use App\Repository\PurchaseRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/store/{store_id}/payment', name: 'app_api_admin_payment_'), IsGranted('ROLE_STORE_ADMIN')]
class PaymentController extends AbstractController
{
	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly PurchaseRepository $purchaseRepository,
		private readonly PaymentManager $paymentManager,
	)
	{
	}

	#[Route('/order-search', name: 'search_orders_for_payment', methods: ['GET'])]
	public function searchOrdersForPayment(
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
		foreach ($this->orderRepository->findChoicesByStoreAndSearch($store, $search) as $order) {
			$results[] = [
				'id' => $order->getId(),
				'text' => $this->orderLabel($order),
				'paymentDefaults' => $this->paymentManager->defaultsForOrder($order),
			];
		}

		return $this->json(['results' => $results]);
	}

	#[Route('/purchase-search', name: 'search_purchases_for_payment', methods: ['GET'])]
	public function searchPurchasesForPayment(
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
		foreach ($this->purchaseRepository->findChoicesByStoreAndSearch($store, $search) as $purchase) {
			$results[] = [
				'id' => $purchase->getId(),
				'text' => $this->purchaseLabel($purchase),
				'paymentDefaults' => $this->paymentManager->defaultsForPurchase($purchase),
			];
		}

		return $this->json(['results' => $results]);
	}

	private function orderLabel(Order $order): string
	{
		return $order->getCustomerNameSnapshot()
			? sprintf('%s — %s', $order->getNumber(), $order->getCustomerNameSnapshot())
			: (string) $order->getNumber();
	}

	private function purchaseLabel(Purchase $purchase): string
	{
		return $purchase->getSupplierNameSnapshot()
			? sprintf('%s — %s', $purchase->getNumber(), $purchase->getSupplierNameSnapshot())
			: (string) $purchase->getNumber();
	}
}

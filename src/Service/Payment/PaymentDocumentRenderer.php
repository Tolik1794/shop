<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Form\Admin\Type\PaymentType;
use App\Manager\PaymentManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class PaymentDocumentRenderer
{
	public const string ORDER_PAYMENT_ACTION_INCOMING = 'incoming';
	public const string ORDER_PAYMENT_ACTION_REFUND = 'refund';

	public function __construct(
		private readonly PaymentManager $paymentManager,
		private readonly OrderPaymentReviewService $orderPaymentReviewService,
		private readonly FormFactoryInterface $formFactory,
		private readonly UrlGeneratorInterface $urlGenerator,
		private readonly Environment $twig,
	)
	{
	}

	public function renderOrder(Order $order, Store $store, Request $request): string
	{
		$payment = $this->paymentManager->createForStore($store);
		$this->paymentManager->prefillFromOrder($payment, $order);

		return $this->render(
			document: $order,
			payment: $payment,
			store: $store,
			request: $request,
			documentRouteParameter: 'order_id',
			indexRoute: 'app_admin_order_index',
		);
	}

	public function renderPurchase(Purchase $purchase, Store $store, Request $request): string
	{
		$payment = $this->paymentManager->createForStore($store);
		$this->paymentManager->prefillFromPurchase($payment, $purchase);

		return $this->render(
			document: $purchase,
			payment: $payment,
			store: $store,
			request: $request,
			documentRouteParameter: 'purchase_id',
			indexRoute: 'app_admin_purchase_index',
		);
	}

	private function render(
		Order|Purchase $document,
		Payment $payment,
		Store $store,
		Request $request,
		string $documentRouteParameter,
		string $indexRoute,
	): string
	{
		$returnUrl = $this->urlGenerator->generate($indexRoute, array_merge($request->query->all(), [
			'store_id' => $store->getId(),
			'id' => $document->getId(),
			'active_tab' => 'payments',
		]));

		$documentRouteParameters = [
			'store_id' => $store->getId(),
			$documentRouteParameter => $document->getId(),
		];

		$lockedFormAction = $this->urlGenerator->generate('app_admin_payment_new', array_merge($documentRouteParameters, [
			'return_url' => $returnUrl,
			'lock_prefilled_document_fields' => 1,
		]));

		$fullFormUrl = $this->urlGenerator->generate('app_admin_payment_new', array_merge($documentRouteParameters, [
			'return_url' => $returnUrl,
		]));
		$paymentActions = $document instanceof Order ? $this->orderPaymentReviewService->paymentActions($document) : null;
		$canAddPayment = $document instanceof Purchase
			|| ($document instanceof Order && $this->orderPaymentReviewService->canRecordIncomingPayment($document));
		$paymentActionUrls = [
			'incoming' => $paymentActions?->incoming !== null
				? $this->urlGenerator->generate('app_admin_payment_new', array_merge($documentRouteParameters, [
					'return_url' => $returnUrl,
					'lock_prefilled_document_fields' => 1,
					'order_payment_action' => self::ORDER_PAYMENT_ACTION_INCOMING,
				]))
				: null,
			'refund' => $paymentActions?->refund !== null
				? $this->urlGenerator->generate('app_admin_payment_new', array_merge($documentRouteParameters, [
					'return_url' => $returnUrl,
					'lock_prefilled_document_fields' => 1,
					'order_payment_action' => self::ORDER_PAYMENT_ACTION_REFUND,
				]))
				: null,
		];

		return $this->twig->render('admin/payment/document.html.twig', [
			'entity' => $document,
			'payments' => $document->getPayments(),
			'payment_form' => $this->formFactory->create(PaymentType::class, $payment, [
				'method' => 'POST',
				'store' => $store,
				'action' => $lockedFormAction,
				'order_ajax_url' => $this->urlGenerator->generate('app_api_admin_payment_search_orders_for_payment', [
					'store_id' => $store->getId(),
				]),
				'purchase_ajax_url' => $this->urlGenerator->generate('app_api_admin_payment_search_purchases_for_payment', [
					'store_id' => $store->getId(),
				]),
				'lock_prefilled_document_fields' => true,
			])->createView(),
			'full_payment_form_url' => $document instanceof Purchase ? $fullFormUrl : null,
			'can_add_payment' => $canAddPayment,
			'payment_actions' => $paymentActions,
			'payment_action_urls' => $paymentActionUrls,
			'payment_review' => $document instanceof Order ? $this->orderPaymentReviewService->canceledPaidReview($document) : null,
		]);
	}
}

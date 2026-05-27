<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Exception\ConcurrencyConflictException;
use App\Form\Admin\FilterType\PaymentFilterType;
use App\Form\Admin\Type\PaymentType;
use App\Manager\PaymentManager;
use App\Repository\OrderRepository;
use App\Repository\PurchaseRepository;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use App\Validator\PaymentBusinessValidator;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/payment', name: 'app_admin_payment_'), IsGranted('payment.view')]
class PaymentController extends AbstractAdvancedController
{
	public function __construct(
		private readonly PaymentManager $paymentManager,
		private readonly PaymentBusinessValidator $paymentBusinessValidator,
		private readonly OrderRepository $orderRepository,
		private readonly PurchaseRepository $purchaseRepository,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		FilterFormHandler $filterFormHandler,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$queryBuilder = $this->paymentManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(PaymentFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);
		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['payment.paidAt'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$payment = $this->paymentManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
			]);
		} else {
			$payment = $pagination->current();
		}

		return $this->render('admin/payment/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $payment,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('payment.create')]
	public function new(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$payment = $this->paymentManager->createForStore($store);
		$this->prefillRequestedDocument($payment, $store, $request);
		$form = $this->createPaymentForm(
			$payment,
			$store,
			lockPrefilledDocumentFields: $request->query->getBoolean('lock_prefilled_document_fields'),
		);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->paymentBusinessValidator->validate($payment, $store, $form) && $form->isValid()) {
			try {
				$this->paymentManager->savePayment($payment);
			} catch (ConcurrencyConflictException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->renderForm($payment, $form, Response::HTTP_CONFLICT);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));

				return $this->renderForm($payment, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
			}

			if (!$request->request->get('save') && $this->isSafeReturnUrl($request)) {
				return $this->redirect((string) $request->query->get('return_url', ''));
			}

			return $this->stayOrRedirect(
				route: 'app_admin_payment_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_payment_show',
				stayParameters: ['store_id' => $store->getId(), 'id' => $payment->getId()],
			);
		}

		return $this->renderForm(
			$payment,
			$form,
			$form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
		);
	}

	#[Route('/order/{order_id}/document', name: 'order_document', methods: ['GET'])]
	public function orderDocument(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		#[MapEntity(expr: 'repository.find(order_id)')]
		Order $order,
	): Response
	{
		if ($order->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		$payment = $this->paymentManager->createForStore($store);
		$this->paymentManager->prefillFromOrder($payment, $order);

		return $this->renderDocumentPayments(
			document: $order,
			payment: $payment,
			store: $store,
			request: $request,
			documentRouteParameter: 'order_id',
			indexRoute: 'app_admin_order_index',
		);
	}

	#[Route('/purchase/{purchase_id}/document', name: 'purchase_document', methods: ['GET'])]
	public function purchaseDocument(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		#[MapEntity(expr: 'repository.find(purchase_id)')]
		Purchase $purchase,
	): Response
	{
		if ($purchase->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		$payment = $this->paymentManager->createForStore($store);
		$this->paymentManager->prefillFromPurchase($payment, $purchase);

		return $this->renderDocumentPayments(
			document: $purchase,
			payment: $payment,
			store: $store,
			request: $request,
			documentRouteParameter: 'purchase_id',
			indexRoute: 'app_admin_purchase_index',
		);
	}

	#[Route('/{id}/reverse', name: 'reverse', methods: ['POST'])]
	#[IsGranted('payment.reverse')]
	public function reverse(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Payment $payment,
	): Response
	{
		$this->denyPaymentOutsideStore($payment, $store);

		if ($this->isCsrfTokenValid('reverse_payment_' . $payment->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->paymentManager->reversePayment($payment, (string) $request->request->get('reason'));
			} catch (ConcurrencyConflictException $exception) {
				$this->addFlash('danger', $exception->getMessage());
			} catch (RuntimeException $exception) {
				$this->addFlash('danger', $exception->getMessage());
			}
		}

		return $this->redirectToRoute('app_admin_payment_index', [
			'store_id' => $store->getId(),
			'id' => $payment->getId(),
		]);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Payment $payment,
	): Response
	{
		$this->denyPaymentOutsideStore($payment, $store);

		return $this->render('admin/payment/show.html.twig', [
			'entity' => $payment,
			'query_params' => $request->query->all(),
		]);
	}

	private function createPaymentForm(
		Payment $payment,
		Store $store,
		?string $action = null,
		bool $lockPrefilledDocumentFields = false,
	): FormInterface
	{
		$options = [
			'method' => 'POST',
			'store' => $store,
			'order_ajax_url' => $this->generateUrl('app_api_admin_payment_search_orders_for_payment', [
				'store_id' => $store->getId(),
			]),
			'purchase_ajax_url' => $this->generateUrl('app_api_admin_payment_search_purchases_for_payment', [
				'store_id' => $store->getId(),
			]),
			'lock_prefilled_document_fields' => $lockPrefilledDocumentFields,
		];

		if ($action !== null) {
			$options['action'] = $action;
		}

		return $this->createForm(PaymentType::class, $payment, $options);
	}

	private function renderForm(Payment $payment, FormInterface $form, int $status): Response
	{
		$response = $this->render('admin/payment/form.html.twig', [
			'entity' => $payment,
			'form' => $form,
		]);
		$response->setStatusCode($status);

		return $response;
	}

	private function prefillRequestedDocument(Payment $payment, Store $store, Request $request): void
	{
		if ($orderId = $request->query->getInt('order_id')) {
			$order = $this->orderRepository->findOneBy([
				'id' => $orderId,
				'store' => $store,
			]);

			if ($order instanceof Order) {
				$this->paymentManager->prefillFromOrder($payment, $order);
			}
		}

		if ($purchaseId = $request->query->getInt('purchase_id')) {
			$purchase = $this->purchaseRepository->findOneBy([
				'id' => $purchaseId,
				'store' => $store,
			]);

			if ($purchase instanceof Purchase) {
				$this->paymentManager->prefillFromPurchase($payment, $purchase);
			}
		}
	}

	private function denyPaymentOutsideStore(Payment $payment, Store $store): void
	{
		if ($payment->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}

	private function isSafeReturnUrl(Request $request): bool
	{
		$returnUrl = (string) $request->query->get('return_url', '');

		if ($returnUrl === '') {
			return false;
		}

		if (str_starts_with($returnUrl, '//')) {
			return false;
		}

		if (str_starts_with($returnUrl, '/')) {
			return true;
		}

		$parts = parse_url($returnUrl);
		if (!is_array($parts) || !isset($parts['host'])) {
			return false;
		}

		return ($parts['scheme'] ?? $request->getScheme()) === $request->getScheme()
			&& $parts['host'] === $request->getHost()
			&& (int) ($parts['port'] ?? $request->getPort()) === $request->getPort();
	}

	private function renderDocumentPayments(
		Order|Purchase $document,
		Payment $payment,
		Store $store,
		Request $request,
		string $documentRouteParameter,
		string $indexRoute,
	): Response
	{
		$returnUrl = $this->generateUrl($indexRoute, array_merge($request->query->all(), [
			'store_id' => $store->getId(),
			'id' => $document->getId(),
			'active_tab' => 'payments',
		]));

		$documentRouteParameters = [
			'store_id' => $store->getId(),
			$documentRouteParameter => $document->getId(),
		];

		$lockedFormAction = $this->generateUrl('app_admin_payment_new', array_merge($documentRouteParameters, [
			'return_url' => $returnUrl,
			'lock_prefilled_document_fields' => 1,
		]));

		$fullFormUrl = $this->generateUrl('app_admin_payment_new', array_merge($documentRouteParameters, [
			'return_url' => $returnUrl,
		]));

		return $this->render('admin/payment/document.html.twig', [
			'entity' => $document,
			'payments' => $document->getPayments(),
			'payment_form' => $this->createPaymentForm(
				payment: $payment,
				store: $store,
				action: $lockedFormAction,
				lockPrefilledDocumentFields: true,
			)->createView(),
			'full_payment_form_url' => $fullFormUrl,
		]);
	}
}

<?php

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\Store;
use App\Form\Admin\FilterType\CustomerFilterType;
use App\Form\Admin\Type\CustomerType;
use App\Manager\CustomerManager;
use App\Service\Customer\CustomerHistoryProvider;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/customer', name: 'app_admin_customer_'), IsGranted('customer.view')]
class CustomerController extends AbstractAdvancedController
{
	public function __construct(
		private readonly CustomerManager $customerManager,
		private readonly CustomerHistoryProvider $customerHistoryProvider,
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
		$queryBuilder = $this->customerManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(CustomerFilterType::class, null, ['store' => $store])->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['customer.name', 'customer.lastName', 'customer.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$customer = $this->customerManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
				'deletedAt' => null,
			]);
		} else {
			$customer = $pagination->current();
		}

		return $this->render('admin/customer/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $customer,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('customer.manage')]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$customer = new Customer();
		$customer->setStore($store);

		$form = $this->createForm(CustomerType::class, $customer, ['method' => 'POST', 'store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->customerManager->save($customer);

			return $this->stayOrRedirect(
				route: 'app_admin_customer_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_customer_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $customer->getId()],
			);
		}

		return $this->render('admin/customer/form.html.twig', [
			'entity' => $customer,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('customer.manage')]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Customer $customer,
	): Response {
		$this->denyCustomerOutsideStore($customer, $store);

		$form = $this->createForm(CustomerType::class, $customer, ['method' => 'POST', 'store' => $store]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->customerManager->save($customer);

			return $this->stayOrRedirect('app_admin_customer_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/customer/form.html.twig', [
			'entity' => $customer,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Customer $customer,
	): Response {
		$this->denyCustomerOutsideStore($customer, $store);

		return $this->render('admin/customer/show.html.twig', [
			'entity' => $customer,
			'query_params' => $request->query->all(),
			'history' => $this->customerHistoryProvider->forCustomer($customer),
		]);
	}

	#[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
	#[IsGranted('customer.manage')]
	public function delete(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Customer $customer,
	): Response {
		$this->denyCustomerOutsideStore($customer, $store);

		if ($this->isCsrfTokenValid('delete_customer_' . $customer->getId(), (string) $request->request->get('_token'))) {
			$this->customerManager->softDelete($customer);
		}

		return $this->redirectToRoute('app_admin_customer_index', ['store_id' => $store->getId()]);
	}

	private function denyCustomerOutsideStore(Customer $customer, Store $store): void
	{
		if ($customer->getStore()?->getId() !== $store->getId() || $customer->getDeletedAt() !== null) {
			throw $this->createNotFoundException();
		}
	}
}

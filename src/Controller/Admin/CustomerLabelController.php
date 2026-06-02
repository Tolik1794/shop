<?php

namespace App\Controller\Admin;

use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Form\Admin\FilterType\CustomerLabelFilterType;
use App\Form\Admin\Type\CustomerLabelType;
use App\Manager\CustomerLabelManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/customer-label', name: 'app_admin_customer_label_'), IsGranted('customer_label.view')]
class CustomerLabelController extends AbstractAdvancedController
{
	public function __construct(private readonly CustomerLabelManager $customerLabelManager)
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
		$queryBuilder = $this->customerLabelManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(CustomerLabelFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['customerLabel.sortOrder', 'customerLabel.name', 'customerLabel.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$customerLabel = $this->customerLabelManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
				'deletedAt' => null,
			]);
		} else {
			$customerLabel = $pagination->current();
		}

		return $this->render('admin/customer_label/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $customerLabel,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('customer_label.manage')]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$customerLabel = new CustomerLabel();
		$customerLabel->setStore($store);

		$form = $this->createForm(CustomerLabelType::class, $customerLabel, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->customerLabelManager->save($customerLabel);

			return $this->stayOrRedirect(
				route: 'app_admin_customer_label_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_customer_label_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $customerLabel->getId()],
			);
		}

		return $this->render('admin/customer_label/form.html.twig', [
			'entity' => $customerLabel,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('customer_label.manage')]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		CustomerLabel $customerLabel,
	): Response {
		$this->denyCustomerLabelOutsideStore($customerLabel, $store);

		$form = $this->createForm(CustomerLabelType::class, $customerLabel, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->customerLabelManager->save($customerLabel);

			return $this->stayOrRedirect('app_admin_customer_label_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/customer_label/form.html.twig', [
			'entity' => $customerLabel,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		CustomerLabel $customerLabel,
	): Response {
		$this->denyCustomerLabelOutsideStore($customerLabel, $store);

		return $this->render('admin/customer_label/show.html.twig', [
			'entity' => $customerLabel,
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
	#[IsGranted('customer_label.manage')]
	public function archive(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		CustomerLabel $customerLabel,
	): Response {
		$this->denyCustomerLabelOutsideStore($customerLabel, $store);

		if ($this->isCsrfTokenValid('archive_customer_label_' . $customerLabel->getId(), (string) $request->request->get('_token'))) {
			$this->customerLabelManager->archive($customerLabel);
		}

		return $this->redirectToRoute('app_admin_customer_label_index', ['store_id' => $store->getId()]);
	}

	private function denyCustomerLabelOutsideStore(CustomerLabel $customerLabel, Store $store): void
	{
		if ($customerLabel->getStore()?->getId() !== $store->getId() || $customerLabel->getDeletedAt() !== null) {
			throw $this->createNotFoundException();
		}
	}
}

<?php

namespace App\Controller\Admin;

use App\Entity\Store;
use App\Entity\Supplier;
use App\Form\Admin\FilterType\SupplierFilterType;
use App\Form\Admin\Type\SupplierType;
use App\Manager\SupplierManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/supplier', name: 'app_admin_supplier_'), IsGranted('ROLE_STORE_ADMIN')]
class SupplierController extends AbstractAdvancedController
{
	public function __construct(private readonly SupplierManager $supplierManager)
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
		$queryBuilder = $this->supplierManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(SupplierFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['supplier.name', 'supplier.id'],
			'defaultSortDirection' => 'asc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$supplier = $this->supplierManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
				'deletedAt' => null,
			]);
		} else {
			$supplier = $pagination->current();
		}

		return $this->render('admin/supplier/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $supplier,
			'filter_form' => $filterForm->createView(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$supplier = new Supplier();
		$supplier->setStore($store);

		$form = $this->createForm(SupplierType::class, $supplier, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->supplierManager->save($supplier);

			return $this->stayOrRedirect(
				route: 'app_admin_supplier_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_supplier_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $supplier->getId()],
			);
		}

		return $this->render('admin/supplier/form.html.twig', [
			'entity' => $supplier,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Supplier $supplier,
	): Response {
		$this->denySupplierOutsideStore($supplier, $store);

		$form = $this->createForm(SupplierType::class, $supplier, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->supplierManager->save($supplier);

			return $this->stayOrRedirect('app_admin_supplier_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/supplier/form.html.twig', [
			'entity' => $supplier,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Supplier $supplier,
	): Response {
		$this->denySupplierOutsideStore($supplier, $store);

		return $this->render('admin/supplier/show.html.twig', [
			'entity' => $supplier,
			'query_params' => $request->query->all(),
		]);
	}

	#[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
	public function delete(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		Supplier $supplier,
	): Response {
		$this->denySupplierOutsideStore($supplier, $store);

		if ($this->isCsrfTokenValid('delete_supplier_' . $supplier->getId(), (string) $request->request->get('_token'))) {
			$this->supplierManager->softDelete($supplier);
		}

		return $this->redirectToRoute('app_admin_supplier_index', ['store_id' => $store->getId()]);
	}

	private function denySupplierOutsideStore(Supplier $supplier, Store $store): void
	{
		if ($supplier->getStore()?->getId() !== $store->getId() || $supplier->getDeletedAt() !== null) {
			throw $this->createNotFoundException();
		}
	}
}

<?php

namespace App\Controller\Admin;

use App\Entity\Store;
use App\Form\Admin\FilterType\StoreFilterType;
use App\Form\Admin\Type\StoreType;
use App\Manager\StoreManager;
use App\Service\FilterFormHandler;
use App\Tools\ControllerTools\ControllerHelperTrait;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/store', name: 'admin_store_'), IsGranted('ROLE_ADMIN')]
class StoreController extends AbstractController
{
	use ControllerHelperTrait;

	public function __construct(private readonly StoreManager $storeManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(PaginatorInterface $paginator, Request $request, FilterFormHandler $filterTypeHandler): Response
	{
		$queryBuilder = $this->storeManager
			->getRepository()
			->createQueryBuilder('store');

		$filterForm = $this->createForm(StoreFilterType::class);
		$filterForm->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['store.name'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($storeId = $request->get('store_id')) {
			$store = $this->storeManager->getRepository()->find($storeId);
		} else {
			$store = $pagination->current();
		}

		return $this->render('admin/store/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $store,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request): Response
	{
		$store = new Store();
		$form = $this->createForm(StoreType::class, $store, [
			'method' => 'POST',
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->storeManager->save($store);

			return $this->stayOrRedirect(
				route: 'admin_store_index',
				stayRoute: 'admin_store_edit',
				stayParameters: ['store_id' => $store->getId()],
			);
		}

		return $this->renderForm('admin/store/form.html.twig', [
			'entity' => $store,
			'form' => $form
		]);
	}

	#[Route('/{store_id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[Entity('store', expr: 'repository.find(store_id)')]
	public function edit(Request $request, Store $store): Response
	{
		$form = $this->createForm(StoreType::class, $store, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			if ($avatar = $form->get('avatar')->getData()) $this->storeManager->updateAvatar($store, $avatar);
			$this->storeManager->save($store);

			return $this->stayOrRedirect('admin_store_index');
		}

		return $this->renderForm('admin/store/form.html.twig', [
			'entity' => $store,
			'form' => $form,
			'avatar' => $this->storeManager->getAvatar($store)?->getPathname()
		]);
	}

	#[Route('/{store_id}/show', name: 'show')]
	#[Entity('store', expr: 'repository.find(store_id)')]
	public function show(Request $request, Store $store): Response
	{
		return $this->render('admin/store/show.html.twig', [
			'entity' => $store,
			'avatar' => $this->storeManager->getAvatar($store)?->getPathname(),
			'query_params' => $request->query->all()
		]);
	}
}

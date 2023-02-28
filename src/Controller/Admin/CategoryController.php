<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Store;
use App\Form\Admin\FilterType\CategoryFilterType;
use App\Form\Admin\Type\CategoryType;
use App\Manager\CategoryManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/store/{store_id}/category', name: 'admin_category_'), IsGranted('ROLE_STORE_ADMIN')]
class CategoryController extends AbstractAdvancedController
{
	public function __construct(private readonly CategoryManager $categoryManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	#[Entity('store', expr: 'repository.find(store_id)')]
	public function index(
		Request $request,
		PaginatorInterface $paginator,
		FilterFormHandler $filterTypeHandler,
		Store $store
	): Response
	{
		$queryBuilder = $this->categoryManager
			->getRepository()
			->findAvailableCategoriesQB($store);

		$filterForm = $this->createForm(CategoryFilterType::class);
		$filterForm->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->get('id')) {
			$category = $this->categoryManager->getRepository()->find($id);
		} else {
			$category = $pagination->current();
		}

		return $this->render('admin/category/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $category,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[isGranted('ROLE_SUPER_ADMIN')]
	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[Entity('store', expr: 'repository.find(store_id)')]
	public function new(Request $request, Store $store): Response
	{
		$category = new Category();
		$category->setStore($store);
		$form = $this->createForm(CategoryType::class, $category, [
			'method' => 'POST',
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->categoryManager->save($category);

			return $this->stayOrRedirect(
				route: 'admin_category_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'admin_category_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $category->getId()],
			);
		}

		return $this->renderForm('admin/category/form.html.twig', [
			'entity' => $category,
			'form' => $form
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[Entity('store', expr: 'repository.find(store_id)')]
	public function edit(Request $request, Category $category, Store $store): Response
	{
//		$this->denyAccessUnlessGranted(StoreVoter::EDIT, $category);
		$form = $this->createForm(CategoryType::class, $category, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->categoryManager->save($category);

			return $this->stayOrRedirect('admin_category_index', ['store_id' => $store->getId()]);
		}

		return $this->renderForm('admin/category/form.html.twig', [
			'entity' => $category,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(Request $request, Category $category): Response
	{
		return $this->render('admin/category/show.html.twig', [
			'entity' => $category,
			'query_params' => $request->query->all()
		]);
	}
}

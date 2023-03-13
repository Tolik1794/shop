<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Store;
use App\Form\CategoryProductParameterNameType;
use App\Repository\CategoryProductParameterNameRepository;
use App\Tools\AbstractAdvancedController;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[
	Route('/admin/store/{store_id}/category/{category_id}/product-parameter-name', name: 'admin_category_product_parameter_name_'),
	Entity("category", expr: "repository.find(category_id)"),
	Entity("store", expr: "repository.find(store_id)")
]
class CategoryProductParameterNameController extends AbstractAdvancedController
{
	public function __construct(private readonly EntityManagerInterface $em)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		CategoryProductParameterNameRepository $categoryProductParameterNameRepository,
		Category                               $category,
		Store                                  $store,
		Request                                $request,
		PaginatorInterface                     $paginator,
	): Response
	{
		$queryBuilder = $categoryProductParameterNameRepository
			->createQueryBuilder('category_product_parameter_name')
			->innerJoin('category_product_parameter_name.category', 'category')
			->where('category_product_parameter_name.category in (:categories)')
			->setParameter(
				'categories',
				$this->em->getRepository(Category::class)->findAllParentIdRecursive($category)
			)
			->orderBy('category.id', 'DESC')
		;

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		return $this->render('admin/category_product_parameter_name/index.html.twig', [
			'store' => $store,
			'category' => $category,
			'pagination' => $pagination,
			'first_entity' => $pagination->current()
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(
		Request                                $request,
		CategoryProductParameterNameRepository $categoryProductParameterNameRepository,
		Category                               $category,
	): Response
	{
		$categoryProductParameterName = new CategoryProductParameterName();
		$categoryProductParameterName->setCategory($category);

		$form = $this->createForm(CategoryProductParameterNameType::class, $categoryProductParameterName);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$categoryProductParameterNameRepository->add($categoryProductParameterName, true);

			return $this->redirectToRoute('admin_category_edit', [
				'store_id' => $category->getStore()->getId(),
				'category_id' => $category->getId()
			], Response::HTTP_SEE_OTHER);
		}

		return $this->render('admin/category_product_parameter_name/form.html.twig', [
//			'category_product_parameter_name' => $categoryProductParameterName,
			'form' => $form,
		]);
	}

	#[Route('/{parameter_name_id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		CategoryProductParameterName $categoryProductParameterName,
		CategoryProductParameterNameRepository $categoryProductParameterNameRepository,
		Category $category,
	): Response
	{
		$form = $this->createForm(CategoryProductParameterNameType::class, $categoryProductParameterName);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$categoryProductParameterNameRepository->add($categoryProductParameterName, true);

			return $this->redirectToRoute('admin_category_edit', [
				'store_id' => $category->getStore()->getId(),
				'category_id' => $category->getId()
			], Response::HTTP_SEE_OTHER);
		}

		return $this->render('admin/category_product_parameter_name/form.html.twig', [
			'form' => $form,
		]);
	}

	#[Route('/{parameter_name_id}', name: 'delete', methods: ['POST'])]
	public function delete(Request $request, CategoryProductParameterName $categoryProductParameterName, CategoryProductParameterNameRepository $categoryProductParameterNameRepository): Response
	{
		if ($this->isCsrfTokenValid('delete' . $categoryProductParameterName->getId(), $request->request->get('_token'))) {
			$categoryProductParameterNameRepository->remove($categoryProductParameterName, true);
		}

		return $this->redirectToRoute('admin_category_product_parameter_name_index', [], Response::HTTP_SEE_OTHER);
	}
}

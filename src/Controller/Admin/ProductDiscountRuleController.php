<?php

namespace App\Controller\Admin;

use App\Entity\ProductDiscountRule;
use App\Entity\ProductDiscountTarget;
use App\Entity\Store;
use App\Form\Admin\Type\ProductDiscountRuleType;
use App\Manager\ProductDiscountRuleManager;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/discount', name: 'app_admin_product_discount_'), IsGranted('product_discount.view')]
class ProductDiscountRuleController extends AbstractAdvancedController
{
	private const int DEFAULT_PAGE_LIMIT = 20;

	public function __construct(private readonly ProductDiscountRuleManager $productDiscountRuleManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$queryBuilder = $this->productDiscountRuleManager
			->getRepository()
			->findAvailableByStoreQB($store);
		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['rule.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$rule = $this->productDiscountRuleManager->getRepository()->findOneBy([
				'id' => $id,
				'store' => $store,
			]);
		} else {
			$rule = $pagination->current();
		}

		return $this->render('admin/product_discount/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $rule,
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('product_discount.manage')]
	public function new(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response
	{
		$rule = $this->productDiscountRuleManager->create($store);
		$form = $this->createRuleForm($rule, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$this->productDiscountRuleManager->saveRule($rule);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect(
					route: 'app_admin_product_discount_index',
					parameters: ['store_id' => $store->getId()],
					stayRoute: 'app_admin_product_discount_edit',
					stayParameters: ['store_id' => $store->getId(), 'id' => $rule->getId()],
				);
			}
		}

		return $this->renderForm($rule, $form, null);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('product_discount.manage')]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductDiscountRule $rule,
	): Response
	{
		$this->denyRuleOutsideStore($rule, $store);
		$originalTargets = [];
		foreach ($rule->getTargets() as $target) {
			$originalTargets[$target->getId()] = $target;
		}

		$form = $this->createRuleForm($rule, $store);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$removedTargets = array_filter(
				$originalTargets,
				static fn (ProductDiscountTarget $target): bool => !$rule->getTargets()->contains($target),
			);

			try {
				$this->productDiscountRuleManager->saveRule($rule, $removedTargets);
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}

			if ($form->isValid()) {
				return $this->stayOrRedirect('app_admin_product_discount_index', ['store_id' => $store->getId()]);
			}
		}

		return $this->renderForm(
			$rule,
			$form,
			$this->productDiscountRuleManager->getRepository()->getIndexPage($rule, self::DEFAULT_PAGE_LIMIT),
		);
	}

	#[Route('/{id}/show', name: 'show', methods: ['GET'])]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ProductDiscountRule $rule,
	): Response
	{
		$this->denyRuleOutsideStore($rule, $store);

		return $this->render('admin/product_discount/show.html.twig', [
			'entity' => $rule,
			'query_params' => $request->query->all(),
		]);
	}

	private function createRuleForm(ProductDiscountRule $rule, Store $store): FormInterface
	{
		return $this->createForm(ProductDiscountRuleType::class, $rule, [
			'method' => 'POST',
			'store' => $store,
			'product_ajax_url' => $this->generateUrl('app_api_admin_product_discount_product_search', [
				'store_id' => $store->getId(),
			]),
			'attr' => [
				'id' => 'product-discount-form',
				'data-select-two-target' => 'form',
			],
		]);
	}

	private function renderForm(ProductDiscountRule $rule, FormInterface $form, ?int $discountIndexPage): Response
	{
		return $this->render('admin/product_discount/form.html.twig', [
			'entity' => $rule,
			'form' => $form,
			'discount_index_page' => $discountIndexPage,
		]);
	}

	private function denyRuleOutsideStore(ProductDiscountRule $rule, Store $store): void
	{
		if ($rule->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}
	}
}

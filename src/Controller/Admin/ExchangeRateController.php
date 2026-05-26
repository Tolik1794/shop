<?php

namespace App\Controller\Admin;

use App\Entity\ExchangeRate;
use App\Entity\Store;
use App\Form\Admin\FilterType\ExchangeRateFilterType;
use App\Form\Admin\Type\ExchangeRateType;
use App\Manager\ExchangeRateManager;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/store/{store_id}/exchange-rate', name: 'app_admin_exchange_rate_'), IsGranted('exchange_rate.manage')]
class ExchangeRateController extends AbstractAdvancedController
{
	public function __construct(private readonly ExchangeRateManager $exchangeRateManager)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		FilterFormHandler $filterFormHandler,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
	): Response {
		$queryBuilder = $this->exchangeRateManager
			->getRepository()
			->findAvailableByStoreQB($store);

		$filterForm = $this->createForm(ExchangeRateFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['exchangeRate.validFrom'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		if ($id = $request->query->get('id')) {
			$exchangeRate = $this->exchangeRateManager->getRepository()->find($id);
		} else {
			$exchangeRate = $pagination->current();
		}

		return $this->render('admin/exchange_rate/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $exchangeRate,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request, #[MapEntity(expr: 'repository.find(store_id)')] Store $store): Response
	{
		$exchangeRate = new ExchangeRate();
		$exchangeRate->setStore($store);

		$form = $this->createForm(ExchangeRateType::class, $exchangeRate, [
			'method' => 'POST',
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->isValidExchangeRate($exchangeRate, $form) && $form->isValid()) {
			$this->exchangeRateManager->saveWithTimeline($exchangeRate);

			return $this->stayOrRedirect(
				route: 'app_admin_exchange_rate_index',
				parameters: ['store_id' => $store->getId()],
				stayRoute: 'app_admin_exchange_rate_edit',
				stayParameters: ['store_id' => $store->getId(), 'id' => $exchangeRate->getId()],
			);
		}

		return $this->render('admin/exchange_rate/form.html.twig', [
			'entity' => $exchangeRate,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ExchangeRate $exchangeRate,
	): Response {
		if ($exchangeRate->getStore() !== null && $exchangeRate->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		$form = $this->createForm(ExchangeRateType::class, $exchangeRate, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $this->isValidExchangeRate($exchangeRate, $form) && $form->isValid()) {
			$this->exchangeRateManager->save($exchangeRate);

			return $this->stayOrRedirect('app_admin_exchange_rate_index', ['store_id' => $store->getId()]);
		}

		return $this->render('admin/exchange_rate/form.html.twig', [
			'entity' => $exchangeRate,
			'form' => $form,
		]);
	}

	#[Route('/{id}/show', name: 'show')]
	public function show(
		Request $request,
		#[MapEntity(expr: 'repository.find(store_id)')]
		Store $store,
		ExchangeRate $exchangeRate,
	): Response {
		if ($exchangeRate->getStore() !== null && $exchangeRate->getStore()?->getId() !== $store->getId()) {
			throw $this->createNotFoundException();
		}

		return $this->render('admin/exchange_rate/show.html.twig', [
			'entity' => $exchangeRate,
			'query_params' => $request->query->all(),
		]);
	}

	private function isValidExchangeRate(ExchangeRate $exchangeRate, FormInterface $form): bool
	{
		if ($exchangeRate->getFromCurrency()?->getCode() === $exchangeRate->getToCurrency()?->getCode()) {
			$form->get('toCurrency')->addError(new FormError('To currency must be different from from currency.'));
		}

		if ((float)$exchangeRate->getRate() <= 0) {
			$form->get('rate')->addError(new FormError('Rate must be greater than zero.'));
		}

		if ($exchangeRate->getId() === null && $this->exchangeRateManager->getRepository()->hasBlockingRateForNewStart($exchangeRate)) {
			$form->addError(new FormError('New exchange rate cannot be created inside an existing rate interval or before a later rate.'));
		}

		if ($exchangeRate->getId() !== null && $this->exchangeRateManager->getRepository()->hasOverlappingRate($exchangeRate)) {
			$form->addError(new FormError('Exchange rate interval overlaps another rate for the same currency pair and store.'));
		}

		return $form->isValid();
	}
}

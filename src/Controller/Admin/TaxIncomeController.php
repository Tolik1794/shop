<?php

namespace App\Controller\Admin;

use App\Entity\IncomeRecord;
use App\Enum\IncomeClassificationEnum;
use App\Form\Admin\FilterType\IncomeRecordFilterType;
use App\Form\Admin\Type\IncomeReclassifyType;
use App\Form\Admin\Type\IncomeRecordType;
use App\Manager\IncomeRecordManager;
use App\Repository\IncomeRecordRepository;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tax/income', name: 'admin_tax_income_'), IsGranted('tax.view')]
class TaxIncomeController extends AbstractAdvancedController
{
	public function __construct(
		private readonly IncomeRecordRepository $incomeRecordRepository,
		private readonly IncomeRecordManager $incomeRecordManager,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(
		PaginatorInterface $paginator,
		Request $request,
		FilterFormHandler $filterFormHandler,
	): Response
	{
		$queryBuilder = $this->incomeRecordRepository->findFilteredQB();

		$filterForm = $this->createForm(IncomeRecordFilterType::class)->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterFormHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$sums = $this->incomeRecordRepository->sumByClassification($queryBuilder);
		$incomeSum = (float) ($sums[IncomeClassificationEnum::INCOME->value] ?? 0);
		$refundSum = (float) ($sums[IncomeClassificationEnum::REFUND->value] ?? 0);

		$page = $request->query->getInt('page', 1);
		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['income_record.recognizedAt', 'income_record.id'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		return $this->render('admin/tax_income/index.html.twig', [
			'pagination' => $pagination,
			'filter_form' => $filterForm->createView(),
			'sums' => $sums,
			'net_income' => number_format($incomeSum - $refundSum, 4, '.', ''),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('tax.income.manage')]
	public function new(Request $request): Response
	{
		$record = new IncomeRecord();
		$form = $this->createForm(IncomeRecordType::class, $record, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$this->incomeRecordManager->saveManual($record);

				return $this->redirectToRoute('admin_tax_income_index');
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}
		}

		return $this->render('admin/tax_income/form.html.twig', [
			'entity' => $record,
			'form' => $form,
		]);
	}

	#[Route('/{id}/reclassify', name: 'reclassify', methods: ['GET', 'POST'])]
	#[IsGranted('tax.income.manage')]
	public function reclassify(Request $request, IncomeRecord $record): Response
	{
		$form = $this->createForm(IncomeReclassifyType::class, [
			'classification' => $record->getClassification(),
		], ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			try {
				$this->incomeRecordManager->reclassify(
					$record,
					$form->get('classification')->getData(),
					$form->get('comment')->getData()
				);

				return $this->redirectToRoute('admin_tax_income_index');
			} catch (RuntimeException $exception) {
				$form->addError(new FormError($exception->getMessage()));
			}
		}

		return $this->render('admin/tax_income/reclassify.html.twig', [
			'entity' => $record,
			'form' => $form,
		]);
	}
}

<?php

namespace App\Controller\Admin;

use App\Entity\LegalEntity;
use App\Entity\TaxReportDraft;
use App\Entity\TaxReportingPeriod;
use App\Entity\User\User;
use App\Manager\TaxPeriodManager;
use App\Manager\TaxReportDraftManager;
use App\Repository\LegalEntityRepository;
use App\Repository\TaxReportDraftRepository;
use App\Repository\TaxReportingPeriodRepository;
use App\Service\Tax\TaxReportGeneratorService;
use App\Tools\AbstractAdvancedController;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tax/reports', name: 'admin_tax_report_'), IsGranted('tax.view')]
class TaxReportController extends AbstractAdvancedController
{
	public function __construct(
		private readonly LegalEntityRepository $legalEntityRepository,
		private readonly TaxReportDraftRepository $draftRepository,
		private readonly TaxReportingPeriodRepository $periodRepository,
		private readonly TaxReportGeneratorService $generatorService,
		private readonly TaxReportDraftManager $draftManager,
		private readonly TaxPeriodManager $periodManager,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(Request $request): Response
	{
		$year = $request->query->getInt('year', (int) (new DateTimeImmutable())->format('Y'));
		$legalEntities = $this->legalEntityRepository->findActive();

		$entityId = $request->query->getInt('entity');
		$currentEntity = null;
		foreach ($legalEntities as $candidate) {
			if ($entityId === 0 || $candidate->getId() === $entityId) {
				$currentEntity = $candidate;
				break;
			}
		}

		return $this->render('admin/tax_report/index.html.twig', [
			'legal_entities' => $legalEntities,
			'current_entity' => $currentEntity,
			'year'           => $year,
			'drafts'         => $currentEntity ? $this->draftRepository->findForEntityYear($currentEntity, $year) : [],
			'periods'        => $currentEntity ? $this->periodRepository->findForEntityYear($currentEntity, $year) : [],
		]);
	}

	#[Route('/generate', name: 'generate', methods: ['POST'])]
	#[IsGranted('tax.reports.manage')]
	public function generate(Request $request): Response
	{
		if (!$this->isCsrfTokenValid('generate_tax_report', (string) $request->request->get('_token'))) {
			return $this->redirectToRoute('admin_tax_report_index');
		}

		$year = $request->request->getInt('year');
		$entity = $this->legalEntityRepository->find($request->request->getInt('entity_id'));
		$quarter = $request->request->get('quarter') !== null && $request->request->get('quarter') !== ''
			? $request->request->getInt('quarter')
			: null;

		if (!$entity instanceof LegalEntity) {
			return $this->redirectToRoute('admin_tax_report_index');
		}

		try {
			$draft = $this->generatorService->generate($entity, $year, $quarter, $this->actor());
		} catch (RuntimeException $exception) {
			$this->addFlash('error', $exception->getMessage());

			return $this->redirectToRoute('admin_tax_report_index', ['entity' => $entity->getId(), 'year' => $year]);
		}

		return $this->redirectToRoute('admin_tax_report_show', ['id' => $draft->getId()]);
	}

	#[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
	public function show(TaxReportDraft $draft): Response
	{
		return $this->render('admin/tax_report/show.html.twig', [
			'draft' => $draft,
		]);
	}

	#[Route('/{id}/update', name: 'update', requirements: ['id' => '\d+'], methods: ['POST'])]
	#[IsGranted('tax.reports.manage')]
	public function update(Request $request, TaxReportDraft $draft): Response
	{
		if (!$this->isCsrfTokenValid('update_tax_report_' . $draft->getId(), (string) $request->request->get('_token'))) {
			return $this->redirectToRoute('admin_tax_report_show', ['id' => $draft->getId()]);
		}

		$submitted = $request->request->all('fields');
		$this->draftManager->applyManualCorrections($draft, array_map('strval', $submitted));

		return $this->redirectToRoute('admin_tax_report_show', ['id' => $draft->getId()]);
	}

	#[Route('/{id}/print', name: 'print', requirements: ['id' => '\d+'], methods: ['GET'])]
	public function print(TaxReportDraft $draft): Response
	{
		$this->draftManager->markExported($draft);

		return $this->render('admin/tax_report/print.html.twig', [
			'draft' => $draft,
		]);
	}

	#[Route('/period/{id}/close', name: 'period_close', requirements: ['id' => '\d+'], methods: ['POST'])]
	#[IsGranted('tax.reports.manage')]
	public function closePeriod(Request $request, TaxReportingPeriod $period): Response
	{
		if ($this->isCsrfTokenValid('tax_period_close_' . $period->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->periodManager->close($period);
			} catch (RuntimeException $exception) {
				$this->addFlash('error', $exception->getMessage());
			}
		}

		return $this->redirectBackToIndex($period);
	}

	#[Route('/period/{id}/declare', name: 'period_declare', requirements: ['id' => '\d+'], methods: ['POST'])]
	#[IsGranted('tax.reports.manage')]
	public function declarePeriod(Request $request, TaxReportingPeriod $period): Response
	{
		if ($this->isCsrfTokenValid('tax_period_declare_' . $period->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->periodManager->markDeclared($period);
			} catch (RuntimeException $exception) {
				$this->addFlash('error', $exception->getMessage());
			}
		}

		return $this->redirectBackToIndex($period);
	}

	#[Route('/period/{id}/reopen', name: 'period_reopen', requirements: ['id' => '\d+'], methods: ['POST'])]
	#[IsGranted('tax.reports.manage')]
	public function reopenPeriod(Request $request, TaxReportingPeriod $period): Response
	{
		if ($this->isCsrfTokenValid('tax_period_reopen_' . $period->getId(), (string) $request->request->get('_token'))) {
			try {
				$this->periodManager->reopen($period, (string) $request->request->get('comment'));
			} catch (RuntimeException $exception) {
				$this->addFlash('error', $exception->getMessage());
			}
		}

		return $this->redirectBackToIndex($period);
	}

	private function redirectBackToIndex(TaxReportingPeriod $period): Response
	{
		return $this->redirectToRoute('admin_tax_report_index', [
			'entity' => $period->getLegalEntity()?->getId(),
			'year'   => $period->getDateFrom()?->format('Y'),
		]);
	}

	private function actor(): ?User
	{
		$user = $this->getUser();

		return $user instanceof User ? $user : null;
	}
}

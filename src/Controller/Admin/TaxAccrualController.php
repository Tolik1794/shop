<?php

namespace App\Controller\Admin;

use App\Entity\LegalEntity;
use App\Entity\TaxAccrual;
use App\Enum\TaxAccrualStatusEnum;
use App\Manager\TaxAccrualManager;
use App\Repository\LegalEntityRepository;
use App\Repository\TaxAccrualRepository;
use App\Service\Tax\TaxAccrualGeneratorService;
use App\Tools\AbstractAdvancedController;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// TODO tax-module phase 6: replace the temporary `dashboard.financial` gate with
// dedicated `tax.*` permissions once they are added to the PermissionCatalog.
#[Route('/admin/tax/accruals', name: 'admin_tax_accrual_'), IsGranted('dashboard.financial')]
class TaxAccrualController extends AbstractAdvancedController
{
	public function __construct(
		private readonly TaxAccrualRepository $accrualRepository,
		private readonly LegalEntityRepository $legalEntityRepository,
		private readonly TaxAccrualGeneratorService $generatorService,
		private readonly TaxAccrualManager $accrualManager,
	) {}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(Request $request): Response
	{
		$year = $request->query->getInt('year', (int) (new DateTimeImmutable())->format('Y'));
		$entityId = $request->query->get('entity');

		$legalEntity = null;
		if ($entityId) {
			$legalEntity = $this->legalEntityRepository->find((int) $entityId);
		}

		$accruals = $legalEntity !== null
			? $this->accrualRepository->findForEntityYear($legalEntity, $year)
			: $this->findAllForYear($year);

		$today = new DateTimeImmutable();

		return $this->render('admin/tax_accrual/index.html.twig', [
			'accruals'        => $accruals,
			'year'            => $year,
			'current_entity'  => $legalEntity,
			'legal_entities'  => $this->legalEntityRepository->findActive(),
			'today'           => $today,
		]);
	}

	#[Route('/generate', name: 'generate', methods: ['POST'])]
	public function generate(Request $request): Response
	{
		if (!$this->isCsrfTokenValid('generate_accruals', (string) $request->request->get('_token'))) {
			return $this->redirectToRoute('admin_tax_accrual_index');
		}

		$year = $request->request->getInt('year', (int) (new DateTimeImmutable())->format('Y'));
		$entityId = $request->request->get('entity_id');

		if ($entityId) {
			$entity = $this->legalEntityRepository->find((int) $entityId);
			if ($entity instanceof LegalEntity) {
				$this->generatorService->generate($entity, $year, apply: true);
			}
		} else {
			$this->generatorService->generateAll($year, apply: true);
		}

		return $this->redirectToRoute('admin_tax_accrual_index', ['year' => $year, 'entity' => $entityId]);
	}

	#[Route('/{id}/mark-paid', name: 'mark_paid', methods: ['POST'])]
	public function markPaid(Request $request, TaxAccrual $accrual): Response
	{
		if (!$this->isCsrfTokenValid('mark_paid_' . $accrual->getId(), (string) $request->request->get('_token'))) {
			return $this->redirectToRoute('admin_tax_accrual_index');
		}

		$paidAt = $request->request->get('paid_at')
			? new DateTimeImmutable((string) $request->request->get('paid_at'))
			: new DateTimeImmutable();

		$paidAmount = $request->request->get('paid_amount')
			? (string) $request->request->get('paid_amount')
			: (string) $accrual->getAccruedAmount();

		$this->accrualManager->markPaid($accrual, $paidAt, $paidAmount);

		return $this->redirectToRoute('admin_tax_accrual_index', [
			'year'   => $accrual->getPeriod()?->getDateFrom()?->format('Y'),
			'entity' => $accrual->getLegalEntity()?->getId(),
		]);
	}

	#[Route('/{id}/reopen', name: 'reopen', methods: ['POST'])]
	public function reopen(Request $request, TaxAccrual $accrual): Response
	{
		if (!$this->isCsrfTokenValid('reopen_accrual_' . $accrual->getId(), (string) $request->request->get('_token'))) {
			return $this->redirectToRoute('admin_tax_accrual_index');
		}

		$this->accrualManager->reopen($accrual);

		return $this->redirectToRoute('admin_tax_accrual_index', [
			'year'   => $accrual->getPeriod()?->getDateFrom()?->format('Y'),
			'entity' => $accrual->getLegalEntity()?->getId(),
		]);
	}

	/** @return TaxAccrual[] */
	private function findAllForYear(int $year): array
	{
		$entities = $this->legalEntityRepository->findActive();
		$all = [];
		foreach ($entities as $entity) {
			array_push($all, ...$this->accrualRepository->findForEntityYear($entity, $year));
		}
		usort($all, static fn(TaxAccrual $a, TaxAccrual $b) => $a->getDueDate() <=> $b->getDueDate());

		return $all;
	}
}

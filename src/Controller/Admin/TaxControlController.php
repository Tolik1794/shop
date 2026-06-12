<?php

namespace App\Controller\Admin;

use App\Entity\LegalEntity;
use App\Repository\IncomeRecordRepository;
use App\Service\Tax\IncomeLimitService;
use App\Tools\AbstractAdvancedController;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tax/control', name: 'admin_tax_control_'), IsGranted('tax.view')]
class TaxControlController extends AbstractAdvancedController
{
	public function __construct(
		private readonly IncomeLimitService $incomeLimitService,
		private readonly IncomeRecordRepository $incomeRecordRepository,
	) {}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(Request $request): Response
	{
		$year = $request->query->getInt('year', (int) (new DateTimeImmutable())->format('Y'));

		return $this->render('admin/tax_control/index.html.twig', [
			'statuses' => $this->incomeLimitService->buildAllStatuses($year),
			'year'     => $year,
		]);
	}

	#[Route('/{id}', name: 'show', methods: ['GET'])]
	public function show(Request $request, LegalEntity $legalEntity): Response
	{
		$year = $request->query->getInt('year', (int) (new DateTimeImmutable())->format('Y'));
		$status = $this->incomeLimitService->buildStatus($legalEntity, $year);
		$byMonth = $this->incomeRecordRepository->getMonthlyBreakdown($legalEntity, $year);

		$byQuarter = $this->buildQuarterlyFromMonthly($byMonth);

		return $this->render('admin/tax_control/show.html.twig', [
			'entity'    => $legalEntity,
			'status'    => $status,
			'year'      => $year,
			'by_month'  => $byMonth,
			'by_quarter' => $byQuarter,
		]);
	}

	/**
	 * @param array<int, array{income: string, refund: string, net: string}> $byMonth
	 * @return array<int, array{income: string, refund: string, net: string}> keyed Q1..Q4
	 */
	private function buildQuarterlyFromMonthly(array $byMonth): array
	{
		$quarters = [];
		foreach ($byMonth as $month => $data) {
			$q = (int) ceil($month / 3);
			if (!isset($quarters[$q])) {
				$quarters[$q] = ['income' => 0.0, 'refund' => 0.0];
			}
			$quarters[$q]['income'] += (float) $data['income'];
			$quarters[$q]['refund'] += (float) $data['refund'];
		}

		$result = [];
		for ($q = 1; $q <= 4; $q++) {
			$inc = $quarters[$q]['income'] ?? 0.0;
			$ref = $quarters[$q]['refund'] ?? 0.0;
			$result[$q] = [
				'income' => number_format($inc, 4, '.', ''),
				'refund' => number_format($ref, 4, '.', ''),
				'net'    => number_format($inc - $ref, 4, '.', ''),
			];
		}

		return $result;
	}
}

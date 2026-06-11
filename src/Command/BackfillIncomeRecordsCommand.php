<?php

namespace App\Command;

use App\Service\Tax\IncomeBackfillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'app:tax:income-backfill',
	description: 'Projects historical payments into tax income records. Reports by default; use --apply to write.',
)]
class BackfillIncomeRecordsCommand extends Command
{
	public function __construct(private readonly IncomeBackfillService $incomeBackfillService)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('apply', null, InputOption::VALUE_NONE, 'Persist the income records instead of reporting only.')
			->addOption('store-id', null, InputOption::VALUE_REQUIRED, 'Limit the backfill to a single store ID.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$apply = (bool) $input->getOption('apply');
		$storeId = $input->getOption('store-id') !== null ? (int) $input->getOption('store-id') : null;

		$report = $this->incomeBackfillService->backfill($apply, $storeId);

		$io->title($apply ? 'Income backfill applied' : 'Income backfill dry run');

		$io->section('Created records by classification');
		$io->table(
			['Classification', 'Count'],
			array_map(static fn (string $key, int $count): array => [$key, $count], array_keys($report['created']), $report['created'])
		);

		$io->section('Skipped payments by reason');
		$io->table(
			['Reason', 'Count'],
			array_map(static fn (string $key, int $count): array => [$key, $count], array_keys($report['skipped']), $report['skipped'])
		);

		$io->section('Net income (income - refund) by legal entity and year, UAH');
		$totalRows = [];
		foreach ($report['totals'] as $entityName => $years) {
			foreach ($years as $year => $sum) {
				$totalRows[] = [$entityName, $year, $sum];
			}
		}
		$io->table(['Legal entity', 'Year', 'Net income (UAH)'], $totalRows);

		if ($report['stores_without_legal_entity'] !== []) {
			$io->warning(sprintf(
				'Stores without a legal entity (payments skipped): %s. Assign Store.legalEntity and re-run.',
				implode(', ', $report['stores_without_legal_entity'])
			));
		}

		$io->success(sprintf(
			'%s: scanned %d payments, %d records %s, %d skipped.',
			$apply ? 'Applied' : 'Dry run',
			$report['scanned'],
			array_sum($report['created']),
			$apply ? 'created' : 'would be created',
			array_sum($report['skipped'])
		));

		return Command::SUCCESS;
	}
}

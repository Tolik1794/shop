<?php

namespace App\Command;

use App\Service\Tax\NbuRateSyncService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
	name: 'app:tax:nbu-rates-sync',
	description: 'Fetches official NBU exchange rates to UAH for configured currencies and stores them for tax income recognition.',
)]
class SyncNbuRatesCommand extends Command
{
	public function __construct(private readonly NbuRateSyncService $nbuRateSyncService)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('date', null, InputOption::VALUE_REQUIRED, 'Sync rates for a single date (YYYY-MM-DD). Defaults to today.')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Range start date (YYYY-MM-DD), used together with --to.')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Range end date (YYYY-MM-DD), used together with --from.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		try {
			$dates = $this->resolveDates($input);
		} catch (Throwable $exception) {
			$io->error($exception->getMessage());

			return Command::INVALID;
		}

		$failures = 0;

		foreach ($dates as $date) {
			try {
				$report = $this->nbuRateSyncService->sync($date);
			} catch (Throwable $exception) {
				$io->error(sprintf('%s: %s', $date->format('Y-m-d'), $exception->getMessage()));
				$failures++;
				continue;
			}

			$io->section(sprintf('NBU rates for %s', $report['date']));
			$io->table(
				['Currency', 'Rate to UAH'],
				array_map(static fn (array $row): array => [$row['currency'], $row['rate']], $report['saved'])
			);

			if ($report['missing'] !== []) {
				$io->warning(sprintf('No NBU rate returned for: %s', implode(', ', $report['missing'])));
			}
		}

		if ($failures > 0) {
			$io->warning(sprintf('%d of %d dates failed to sync.', $failures, count($dates)));

			return Command::FAILURE;
		}

		$io->success(sprintf('Synced NBU rates for %d date(s).', count($dates)));

		return Command::SUCCESS;
	}

	/**
	 * @return DateTimeImmutable[]
	 */
	private function resolveDates(InputInterface $input): array
	{
		$from = $input->getOption('from');
		$to = $input->getOption('to');

		if (($from === null) !== ($to === null)) {
			throw new \InvalidArgumentException('Options --from and --to must be used together.');
		}

		if ($from !== null && $to !== null) {
			$start = new DateTimeImmutable($from);
			$end = new DateTimeImmutable($to);

			if ($start > $end) {
				throw new \InvalidArgumentException('--from must not be later than --to.');
			}

			$dates = [];
			for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
				$dates[] = $date;
			}

			return $dates;
		}

		return [new DateTimeImmutable((string) ($input->getOption('date') ?? 'today'))];
	}
}

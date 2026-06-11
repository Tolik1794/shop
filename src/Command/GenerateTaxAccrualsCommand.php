<?php

namespace App\Command;

use App\Repository\LegalEntityRepository;
use App\Service\Tax\TaxAccrualGeneratorService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'app:tax:generate-accruals',
	description: 'Generate (or update) tax accruals for legal entities. Dry-run by default.',
)]
class GenerateTaxAccrualsCommand extends Command
{
	public function __construct(
		private readonly TaxAccrualGeneratorService $generatorService,
		private readonly LegalEntityRepository $legalEntityRepository,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('apply', null, InputOption::VALUE_NONE, 'Actually write accruals (omit for dry-run report)')
			->addOption('year', null, InputOption::VALUE_REQUIRED, 'Year to generate accruals for', (int) (new DateTimeImmutable())->format('Y'))
			->addOption('legal-entity-id', null, InputOption::VALUE_REQUIRED, 'Generate only for this legal entity ID');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$apply = (bool) $input->getOption('apply');
		$year = (int) $input->getOption('year');
		$entityId = $input->getOption('legal-entity-id') !== null ? (int) $input->getOption('legal-entity-id') : null;

		$io->title(sprintf(
			'Tax accruals generation — %d (%s)',
			$year,
			$apply ? 'APPLY' : 'DRY RUN',
		));

		if ($entityId !== null) {
			$entity = $this->legalEntityRepository->find($entityId);
			if ($entity === null) {
				$io->error(sprintf('Legal entity #%d not found.', $entityId));

				return Command::FAILURE;
			}
			$results = [(string) $entity => $this->generatorService->generate($entity, $year, $apply)];
		} else {
			$results = $this->generatorService->generateAll($year, $apply);
		}

		$rows = [];
		$totalCreated = 0;
		$totalUpdated = 0;

		foreach ($results as $name => $report) {
			$rows[] = [
				$name,
				$report['created'],
				$report['updated'],
				$report['skipped'],
				implode('; ', $report['errors']) ?: '—',
			];
			$totalCreated += $report['created'];
			$totalUpdated += $report['updated'];
		}

		$io->table(['Legal entity', 'Created', 'Updated', 'Skipped (paid)', 'Errors'], $rows);
		$io->writeln(sprintf('Total: <info>%d</info> created, <comment>%d</comment> updated.', $totalCreated, $totalUpdated));

		if (!$apply) {
			$io->note('Dry run — no changes written. Re-run with --apply to persist.');
		} else {
			$io->success('Accruals generated successfully.');
		}

		return Command::SUCCESS;
	}
}

<?php

namespace App\Command;

use App\Service\WarehouseStockReconciler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'app:warehouse-stock:reconcile',
	description: 'Finds and optionally repairs stock, movement balance, and batch remaining quantity drift.',
)]
class ReconcileWarehouseStockCommand extends Command
{
	public function __construct(private readonly WarehouseStockReconciler $reconciler)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('apply', null, InputOption::VALUE_NONE, 'Apply safe reported changes.')
			->addOption('store-id', null, InputOption::VALUE_REQUIRED, 'Limit reconciliation to a store ID.')
			->addOption('stock-id', null, InputOption::VALUE_REQUIRED, 'Limit reconciliation to a warehouse stock ID.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$apply = (bool) $input->getOption('apply');
		$storeId = $this->positiveId($input->getOption('store-id'), 'store-id', $io);
		$stockId = $this->positiveId($input->getOption('stock-id'), 'stock-id', $io);
		if ($storeId === false || $stockId === false) {
			return Command::INVALID;
		}

		$report = $this->reconciler->reconcile($storeId, $stockId, $apply);
		$io->title($apply ? 'Warehouse stock reconciliation applied' : 'Warehouse stock reconciliation dry run');
		$io->table(
			['Stock ID', 'Product', 'Stored', 'Expected', 'Stock', 'Batches', 'Movements', 'Opening batch', 'Safe', 'Reason'],
			array_map(static fn (array $row): array => [
				$row['stock_id'],
				$row['product'],
				$row['stored'],
				$row['expected'],
				$row['stock_changed'] ? 'change' : '',
				$row['batch_changes'],
				$row['movement_changes'],
				$row['opening_batch_created'] ? 'create' : '',
				$row['safe'] ? 'yes' : 'no',
				$row['reason'],
			], $report['stocks'])
		);

		$unsafe = count(array_filter($report['stocks'], static fn (array $row): bool => !$row['safe']));
		$io->success(sprintf(
			'%s: %d warehouse stocks require attention; %d are unsafe and were not changed.',
			$apply ? 'Applied' : 'Dry run',
			count($report['stocks']),
			$unsafe,
		));

		return $unsafe > 0 ? Command::FAILURE : Command::SUCCESS;
	}

	private function positiveId(mixed $value, string $option, SymfonyStyle $io): int|false|null
	{
		if ($value === null) {
			return null;
		}

		if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
			$io->error(sprintf('Option --%s must be a positive integer.', $option));

			return false;
		}

		return (int) $value;
	}
}

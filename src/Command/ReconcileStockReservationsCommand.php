<?php

namespace App\Command;

use App\Service\StockReservationReconciler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
	name: 'app:stock-reservations:reconcile',
	description: 'Finds and optionally repairs stale stock reservations and aggregate reserved quantities.',
)]
class ReconcileStockReservationsCommand extends Command
{
	public function __construct(private readonly StockReservationReconciler $reconciler)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('apply', null, InputOption::VALUE_NONE, 'Apply the reported changes.')
			->addOption('store-id', null, InputOption::VALUE_REQUIRED, 'Limit reconciliation to a store ID.')
			->addOption('order-id', null, InputOption::VALUE_REQUIRED, 'Limit reconciliation to an order ID.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$apply = (bool) $input->getOption('apply');
		$storeId = $this->positiveId($input->getOption('store-id'), 'store-id', $io);
		$orderId = $this->positiveId($input->getOption('order-id'), 'order-id', $io);
		if ($storeId === false || $orderId === false) {
			return Command::INVALID;
		}

		$report = $this->reconciler->reconcile($storeId, $orderId, $apply);
		$io->title($apply ? 'Stock reservation reconciliation applied' : 'Stock reservation reconciliation dry run');

		$io->section('Reservation changes');
		$io->table(
			['Order', 'Order ID', 'Entry ID', 'Active', 'Allowed active', 'Complete', 'Cancel'],
			array_map(static fn (array $row): array => [
				$row['order'],
				$row['order_id'],
				$row['entry_id'],
				$row['active'],
				$row['allowed_active'],
				$row['complete'],
				$row['cancel'],
			], $report['entries'])
		);

		$io->section('Warehouse stock aggregate changes');
		$io->table(
			['Stock ID', 'Stored reserved', 'Active reservation total'],
			array_map(static fn (array $row): array => [$row['stock_id'], $row['stored'], $row['actual']], $report['stocks'])
		);

		$io->success(sprintf(
			'%s: %d order entries and %d warehouse stock aggregates require changes.',
			$apply ? 'Applied' : 'Dry run',
			count($report['entries']),
			count($report['stocks'])
		));

		return Command::SUCCESS;
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

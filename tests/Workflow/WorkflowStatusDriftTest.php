<?php

namespace App\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class WorkflowStatusDriftTest extends TestCase
{
	public function testDirectStatusWritesStayInApprovedWorkflowBoundaries(): void
	{
		$allowedFiles = [
			'src/DataFixtures/CurrencyFixtures.php',
			'src/DataFixtures/CustomerLabelFixtures.php',
			'src/DataFixtures/ProductFixtures.php',
			'src/DataFixtures/StoreFixtures.php',
			'src/DataFixtures/UnitFixtures.php',
			'src/Manager/TaxAccrualManager.php',
			'src/Service/BusinessDocumentStatusSynchronizer.php',
			'src/Service/InventoryPostingService.php',
			'src/Service/Lifecycle/ReferenceArchivePolicy.php',
			'src/Service/StockReservationService.php',
		];
		$violations = [];
		$sourceDir = __DIR__ . '/../../src/';
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));

		foreach ($iterator as $file) {
			if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
				continue;
			}

			$relativePath = 'src/' . str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDir)));
			if (in_array($relativePath, $allowedFiles, true)) {
				continue;
			}

			$contents = file_get_contents($file->getPathname());
			if ($contents !== false && preg_match('/->setStatus\(/', $contents) === 1) {
				$violations[] = $relativePath;
			}
		}

		self::assertSame([], $violations);
	}

	public function testInventoryDocumentStatusWritesStayInsidePostingService(): void
	{
		$allowedFiles = [
			'src/Service/InventoryPostingService.php',
		];
		$violations = [];
		$sourceDir = __DIR__ . '/../../src/';
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));

		foreach ($iterator as $file) {
			if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
				continue;
			}

			$relativePath = 'src/' . str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDir)));
			if (in_array($relativePath, $allowedFiles, true)) {
				continue;
			}

			$contents = file_get_contents($file->getPathname());
			if ($contents !== false && preg_match('/->setStatus\\(\\s*InventoryDocumentStatus::/', $contents) === 1) {
				$violations[] = $relativePath;
			}
		}

		self::assertSame([], $violations);
	}

	public function testInventoryDocumentHasNoGenericWorkflowDefinition(): void
	{
		$definitionDir = __DIR__ . '/../../src/Workflow/Definition/';
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($definitionDir));
		$violations = [];

		foreach ($iterator as $file) {
			if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
				continue;
			}

			$contents = file_get_contents($file->getPathname());
			if ($contents !== false && str_contains($contents, 'InventoryDocument')) {
				$violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__ . '/../../')));
			}
		}

		self::assertSame([], $violations);
	}
}

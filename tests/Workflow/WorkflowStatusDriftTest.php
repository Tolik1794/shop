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
			'src/DataFixtures/StoreFixtures.php',
			'src/DataFixtures/UnitFixtures.php',
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
}

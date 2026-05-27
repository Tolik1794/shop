<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527122000 extends AbstractMigration
{
	/**
	 * @var list<array{0: string, 1: string}>
	 */
	private const INDEX_RENAMES = [
		['idx_6c2330e1fade5cb7', 'idx_6c2330e775e8d6dc'],
		['idx_9fd5553ab092a811', 'idx_1528256ab092a811'],
		['idx_9fd5553a4584665a', 'idx_1528256a4584665a'],
		['idx_b5d8c39959d8a214', 'idx_7bb94a959d8a214'],
		['idx_6b7c50ebfade5cb7', 'idx_3abb4df875e8d6dc'],
		['idx_6b7c50eb48ec5212', 'idx_3abb4df85e8b5c1e'],
		['idx_6b4e96e1b092a811', 'idx_ef2857aeb092a811'],
		['idx_6b4e96e1b03a8386', 'idx_ef2857aeb03a8386'],
		['idx_6b4e96e1896dbbde', 'idx_ef2857ae896dbbde'],
		['idx_6b4e96e1b3ba5a5a', 'idx_ef2857ae9740c9d5'],
		['idx_6b4e96e1fa67b0f2', 'idx_ef2857ae85ecde76'],
		['idx_6b4e96e11418957', 'idx_ef2857ae1418957'],
		['idx_6b4e96e14584665a', 'idx_ef2857ae4584665a'],
		['idx_6b4e96e15080ecde', 'idx_ef2857ae5080ecde'],
		['idx_6b4e96e159d8a214', 'idx_ef2857ae59d8a214'],
	];

	public function getDescription(): string
	{
		return 'Normalize legacy test database index names to current Doctrine metadata.';
	}

	public function up(Schema $schema): void
	{
		foreach (self::INDEX_RENAMES as [$oldName, $newName]) {
			$this->renameIndexIfNeeded($oldName, $newName);
		}
	}

	public function down(Schema $schema): void
	{
		foreach (array_reverse(self::INDEX_RENAMES) as [$oldName, $newName]) {
			$this->renameIndexIfNeeded($newName, $oldName);
		}
	}

	private function renameIndexIfNeeded(string $oldName, string $newName): void
	{
		$this->addSql(sprintf(
			"DO $$ BEGIN IF to_regclass('public.%s') IS NOT NULL AND to_regclass('public.%s') IS NULL THEN ALTER INDEX %s RENAME TO %s; END IF; END $$;",
			$oldName,
			$newName,
			$oldName,
			$newName
		));
	}
}

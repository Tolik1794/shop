<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add optimistic versions and stock integrity constraints for concurrency safety.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE orders ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE purchase ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE production_order ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE inventory_document ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE payment ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE warehouse_stock ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');
		$this->addSql('ALTER TABLE warehouse_stock_batch ADD COLUMN IF NOT EXISTS version INT DEFAULT 1 NOT NULL');

		$this->addCheckConstraintIfMissing('warehouse_stock', 'chk_warehouse_stock_quantity_on_hand_non_negative', 'quantity_on_hand >= 0');
		$this->addCheckConstraintIfMissing('warehouse_stock', 'chk_warehouse_stock_reserved_quantity_non_negative', 'reserved_quantity >= 0');
		$this->addCheckConstraintIfMissing('warehouse_stock', 'chk_warehouse_stock_reserved_not_above_on_hand', 'reserved_quantity <= quantity_on_hand');
		$this->addCheckConstraintIfMissing('warehouse_stock_batch', 'chk_warehouse_stock_batch_initial_quantity_non_negative', 'initial_quantity >= 0');
		$this->addCheckConstraintIfMissing('warehouse_stock_batch', 'chk_warehouse_stock_batch_remaining_quantity_non_negative', 'remaining_quantity >= 0');
		$this->addCheckConstraintIfMissing('warehouse_stock_batch', 'chk_warehouse_stock_batch_remaining_not_above_initial', 'remaining_quantity <= initial_quantity');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT IF EXISTS chk_warehouse_stock_batch_remaining_not_above_initial');
		$this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT IF EXISTS chk_warehouse_stock_batch_remaining_quantity_non_negative');
		$this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT IF EXISTS chk_warehouse_stock_batch_initial_quantity_non_negative');
		$this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT IF EXISTS chk_warehouse_stock_reserved_not_above_on_hand');
		$this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT IF EXISTS chk_warehouse_stock_reserved_quantity_non_negative');
		$this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT IF EXISTS chk_warehouse_stock_quantity_on_hand_non_negative');

		$this->addSql('ALTER TABLE warehouse_stock_batch DROP COLUMN IF EXISTS version');
		$this->addSql('ALTER TABLE warehouse_stock DROP COLUMN IF EXISTS version');
		$this->addSql('ALTER TABLE payment DROP COLUMN IF EXISTS version');
		$this->addSql('ALTER TABLE inventory_document DROP COLUMN IF EXISTS version');
		$this->addSql('ALTER TABLE production_order DROP COLUMN IF EXISTS version');
		$this->addSql('ALTER TABLE purchase DROP COLUMN IF EXISTS version');
		$this->addSql('ALTER TABLE orders DROP COLUMN IF EXISTS version');
	}

	private function addCheckConstraintIfMissing(string $table, string $constraint, string $condition): void
	{
		$this->addSql(sprintf(
			"DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '%s') THEN ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s); END IF; END $$;",
			$constraint,
			$table,
			$constraint,
			$condition
		));
	}
}

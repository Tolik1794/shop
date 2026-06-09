<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608124500 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Align linked production active-demand partial index with Doctrine mapping.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('DROP INDEX uniq_production_order_active_source_entry');
		$this->addSql("CREATE UNIQUE INDEX uniq_production_order_active_source_entry ON production_order (source_order_entry_id) WHERE (source_order_entry_id IS NOT NULL AND status IN ('draft', 'planned', 'materials_reserved', 'in_progress'))");
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP INDEX uniq_production_order_active_source_entry');
		$this->addSql("CREATE UNIQUE INDEX uniq_production_order_active_source_entry ON production_order (source_order_entry_id) WHERE source_order_entry_id IS NOT NULL AND status IN ('draft', 'planned', 'materials_reserved', 'in_progress')");
	}
}

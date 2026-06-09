<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add explicit order entry fulfillment source and link production orders to sales demand.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql("ALTER TABLE order_entry ADD fulfillment_source VARCHAR(32) DEFAULT 'stock' NOT NULL");
		$this->addSql("UPDATE order_entry oe SET fulfillment_source = 'service' FROM product p WHERE oe.product_id = p.id AND p.product_kind = 'service'");
		$this->addSql("UPDATE order_entry SET fulfillment_source = 'production' WHERE warehouse_id IS NULL AND fulfillment_source <> 'service'");
		$this->addSql('ALTER TABLE order_entry ALTER fulfillment_source DROP DEFAULT');
		$this->addSql('ALTER TABLE production_order ADD source_order_entry_id INT DEFAULT NULL');
		$this->addSql('ALTER TABLE production_order ADD CONSTRAINT FK_EF2857AEF8B7C756 FOREIGN KEY (source_order_entry_id) REFERENCES order_entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
		$this->addSql('CREATE INDEX idx_production_order_source_entry ON production_order (source_order_entry_id)');
		$this->addSql("CREATE UNIQUE INDEX uniq_production_order_active_source_entry ON production_order (source_order_entry_id) WHERE source_order_entry_id IS NOT NULL AND status IN ('draft', 'planned', 'materials_reserved', 'in_progress')");
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP INDEX uniq_production_order_active_source_entry');
		$this->addSql('DROP INDEX idx_production_order_source_entry');
		$this->addSql('ALTER TABLE production_order DROP CONSTRAINT FK_EF2857AEF8B7C756');
		$this->addSql('ALTER TABLE production_order DROP source_order_entry_id');
		$this->addSql('ALTER TABLE order_entry DROP fulfillment_source');
	}
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527121000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Keep optimistic version defaults in sync with Doctrine metadata.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE orders ALTER version SET DEFAULT 1');
		$this->addSql('ALTER TABLE purchase ALTER version SET DEFAULT 1');
		$this->addSql('ALTER TABLE production_order ALTER version SET DEFAULT 1');
		$this->addSql('ALTER TABLE inventory_document ALTER version SET DEFAULT 1');
		$this->addSql('ALTER TABLE payment ALTER version SET DEFAULT 1');
		$this->addSql('ALTER TABLE warehouse_stock ALTER version SET DEFAULT 1');
		$this->addSql('ALTER TABLE warehouse_stock_batch ALTER version SET DEFAULT 1');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE warehouse_stock_batch ALTER version DROP DEFAULT');
		$this->addSql('ALTER TABLE warehouse_stock ALTER version DROP DEFAULT');
		$this->addSql('ALTER TABLE payment ALTER version DROP DEFAULT');
		$this->addSql('ALTER TABLE inventory_document ALTER version DROP DEFAULT');
		$this->addSql('ALTER TABLE production_order ALTER version DROP DEFAULT');
		$this->addSql('ALTER TABLE purchase ALTER version DROP DEFAULT');
		$this->addSql('ALTER TABLE orders ALTER version DROP DEFAULT');
	}
}

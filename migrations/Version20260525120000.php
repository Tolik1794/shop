<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Link order entries and stock reservations to selected warehouse stock batches.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE order_entry ADD warehouse_stock_batch_id INT DEFAULT NULL');
		$this->addSql('CREATE INDEX IDX_A8BFE98D9DA9F6D1 ON order_entry (warehouse_stock_batch_id)');
		$this->addSql('ALTER TABLE order_entry ADD CONSTRAINT FK_A8BFE98D9DA9F6D1 FOREIGN KEY (warehouse_stock_batch_id) REFERENCES warehouse_stock_batch (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
		$this->addSql('ALTER TABLE stock_reservation ADD warehouse_stock_batch_id INT DEFAULT NULL');
		$this->addSql('CREATE INDEX IDX_9D06EF619DA9F6D1 ON stock_reservation (warehouse_stock_batch_id)');
		$this->addSql('CREATE INDEX idx_stock_reservation_batch_status ON stock_reservation (warehouse_stock_batch_id, status)');
		$this->addSql('ALTER TABLE stock_reservation ADD CONSTRAINT FK_9D06EF619DA9F6D1 FOREIGN KEY (warehouse_stock_batch_id) REFERENCES warehouse_stock_batch (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE order_entry DROP CONSTRAINT FK_A8BFE98D9DA9F6D1');
		$this->addSql('ALTER TABLE stock_reservation DROP CONSTRAINT FK_9D06EF619DA9F6D1');
		$this->addSql('DROP INDEX IDX_A8BFE98D9DA9F6D1');
		$this->addSql('DROP INDEX IDX_9D06EF619DA9F6D1');
		$this->addSql('DROP INDEX idx_stock_reservation_batch_status');
		$this->addSql('ALTER TABLE order_entry DROP warehouse_stock_batch_id');
		$this->addSql('ALTER TABLE stock_reservation DROP warehouse_stock_batch_id');
	}
}

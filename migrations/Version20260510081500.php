<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510081500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add warehouse stock and batch business constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouse_stock ADD CONSTRAINT chk_warehouse_stock_reserved_lte_on_hand CHECK (reserved_quantity <= quantity_on_hand)');
        $this->addSql('ALTER TABLE warehouse_stock_batch ADD CONSTRAINT chk_warehouse_stock_batch_initial_quantity_non_negative CHECK (initial_quantity >= 0)');
        $this->addSql('ALTER TABLE warehouse_stock_batch ADD CONSTRAINT chk_warehouse_stock_batch_remaining_quantity_non_negative CHECK (remaining_quantity >= 0)');
        $this->addSql('ALTER TABLE warehouse_stock_batch ADD CONSTRAINT chk_warehouse_stock_batch_remaining_lte_initial CHECK (remaining_quantity <= initial_quantity)');
        $this->addSql('ALTER TABLE warehouse_stock_batch ADD CONSTRAINT chk_warehouse_stock_batch_unit_cost_non_negative CHECK (unit_cost >= 0)');
        $this->addSql('ALTER TABLE warehouse_stock_batch ADD CONSTRAINT chk_warehouse_stock_batch_sale_price_non_negative CHECK (sale_price IS NULL OR sale_price >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT chk_warehouse_stock_batch_sale_price_non_negative');
        $this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT chk_warehouse_stock_batch_unit_cost_non_negative');
        $this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT chk_warehouse_stock_batch_remaining_lte_initial');
        $this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT chk_warehouse_stock_batch_remaining_quantity_non_negative');
        $this->addSql('ALTER TABLE warehouse_stock_batch DROP CONSTRAINT chk_warehouse_stock_batch_initial_quantity_non_negative');
        $this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT chk_warehouse_stock_reserved_lte_on_hand');
    }
}

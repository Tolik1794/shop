<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509080910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add non-negative checks for warehouse stock quantities and cost.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouse_stock ADD CONSTRAINT chk_warehouse_stock_quantity_on_hand_non_negative CHECK (quantity_on_hand >= 0)');
        $this->addSql('ALTER TABLE warehouse_stock ADD CONSTRAINT chk_warehouse_stock_reserved_quantity_non_negative CHECK (reserved_quantity >= 0)');
        $this->addSql('ALTER TABLE warehouse_stock ADD CONSTRAINT chk_warehouse_stock_average_cost_non_negative CHECK (average_cost >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT chk_warehouse_stock_quantity_on_hand_non_negative');
        $this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT chk_warehouse_stock_reserved_quantity_non_negative');
        $this->addSql('ALTER TABLE warehouse_stock DROP CONSTRAINT chk_warehouse_stock_average_cost_non_negative');
    }
}

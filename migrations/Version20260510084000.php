<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510084000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sales order business constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_orders_total_amount_non_negative CHECK (total_amount >= 0)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_orders_total_amount_base_non_negative CHECK (total_amount_base >= 0)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_orders_discount_amount_non_negative CHECK (discount_amount IS NULL OR discount_amount >= 0)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_orders_discount_amount_base_non_negative CHECK (discount_amount_base IS NULL OR discount_amount_base >= 0)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_orders_paid_amount_base_non_negative CHECK (paid_amount_base >= 0)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_orders_exchange_rate_to_base_positive CHECK (exchange_rate_to_base > 0)');

        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_quantity_positive CHECK (quantity > 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_shipped_quantity_non_negative CHECK (shipped_quantity IS NULL OR shipped_quantity >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_returned_quantity_non_negative CHECK (returned_quantity IS NULL OR returned_quantity >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_canceled_quantity_non_negative CHECK (canceled_quantity IS NULL OR canceled_quantity >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_unit_price_non_negative CHECK (unit_price >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_unit_price_base_non_negative CHECK (unit_price_base >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_discount_amount_non_negative CHECK (discount_amount IS NULL OR discount_amount >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_discount_amount_base_non_negative CHECK (discount_amount_base IS NULL OR discount_amount_base >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_total_price_non_negative CHECK (total_price >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_total_price_base_non_negative CHECK (total_price_base >= 0)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_shipped_lte_quantity CHECK (shipped_quantity IS NULL OR shipped_quantity <= quantity)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_returned_lte_quantity CHECK (returned_quantity IS NULL OR returned_quantity <= quantity)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT chk_order_entry_canceled_lte_quantity CHECK (canceled_quantity IS NULL OR canceled_quantity <= quantity)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_canceled_lte_quantity');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_returned_lte_quantity');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_shipped_lte_quantity');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_total_price_base_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_total_price_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_discount_amount_base_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_discount_amount_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_unit_price_base_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_unit_price_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_canceled_quantity_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_returned_quantity_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_shipped_quantity_non_negative');
        $this->addSql('ALTER TABLE order_entry DROP CONSTRAINT chk_order_entry_quantity_positive');

        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_exchange_rate_to_base_positive');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_paid_amount_base_non_negative');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_discount_amount_base_non_negative');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_discount_amount_non_negative');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_total_amount_base_non_negative');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_orders_total_amount_non_negative');
    }
}

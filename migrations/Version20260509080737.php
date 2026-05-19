<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509080737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE category ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE category ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER quantity DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER unit_price DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER unit_price_base DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER total_price DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER total_price_base DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER product_name_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER product_code_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER unit_code_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE order_entry ALTER unit_name_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER number DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER exchange_rate_to_base DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER total_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER total_amount_base DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER paid_amount_base DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER payment_status DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ALTER currency_id DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER number DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER exchange_rate_to_base DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER total_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER total_amount_base DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER paid_amount_base DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER payment_status DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ALTER currency_id DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER quantity DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_cost DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_cost_base DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER total_cost DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER total_cost_base DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER product_name_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER product_code_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_code_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_name_snapshot DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse_stock ALTER reserved_quantity DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse_stock ALTER average_cost DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse_stock ALTER quantity_on_hand DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse_stock ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse_stock ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE warehouse_stock_batch ALTER created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ALTER status SET DEFAULT \'active\'');
        $this->addSql('ALTER TABLE category ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE category ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE order_entry ALTER quantity SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE order_entry ALTER unit_price SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE order_entry ALTER unit_price_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE order_entry ALTER total_price SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE order_entry ALTER total_price_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE order_entry ALTER product_name_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE order_entry ALTER product_code_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE order_entry ALTER unit_code_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE order_entry ALTER unit_name_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE orders ALTER number SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE orders ALTER exchange_rate_to_base SET DEFAULT \'1.00000000\'');
        $this->addSql('ALTER TABLE orders ALTER total_amount SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE orders ALTER total_amount_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE orders ALTER paid_amount_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE orders ALTER payment_status SET DEFAULT \'unpaid\'');
        $this->addSql('ALTER TABLE orders ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE orders ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE orders ALTER currency_id SET DEFAULT \'UAH\'');
        $this->addSql('ALTER TABLE purchase ALTER number SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE purchase ALTER exchange_rate_to_base SET DEFAULT \'1.00000000\'');
        $this->addSql('ALTER TABLE purchase ALTER total_amount SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase ALTER total_amount_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase ALTER paid_amount_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase ALTER payment_status SET DEFAULT \'unpaid\'');
        $this->addSql('ALTER TABLE purchase ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE purchase ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE purchase ALTER currency_id SET DEFAULT \'UAH\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER quantity SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_cost SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_cost_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER total_cost SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER total_cost_base SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER product_name_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER product_code_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_code_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE purchase_entry ALTER unit_name_snapshot SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE warehouse ALTER status SET DEFAULT \'active\'');
        $this->addSql('ALTER TABLE warehouse ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE warehouse ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE warehouse_stock ALTER quantity_on_hand SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE warehouse_stock ALTER reserved_quantity SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE warehouse_stock ALTER average_cost SET DEFAULT \'0.0000\'');
        $this->addSql('ALTER TABLE warehouse_stock ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE warehouse_stock ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE warehouse_stock_batch ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220822063505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE additional_name (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category_additional_name (id INT AUTO_INCREMENT NOT NULL, store_id INT NOT NULL, category_id INT NOT NULL, additional_name_id INT NOT NULL, INDEX IDX_283EA49AB092A811 (store_id), INDEX IDX_283EA49A12469DE2 (category_id), INDEX IDX_283EA49A709D4F22 (additional_name_id), UNIQUE INDEX UNIQ_283EA49AB092A811709D4F2212469DE2 (store_id, additional_name_id, category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE entry (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, cost NUMERIC(14, 4) NOT NULL, delivery_cost NUMERIC(10, 4) DEFAULT NULL, INDEX IDX_2B219D704584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, store_id INT NOT NULL, status VARCHAR(128) NOT NULL, INDEX IDX_F5299398B092A811 (store_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_entry (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, entry_id INT NOT NULL, INDEX IDX_A8BFE98D8D9F6D38 (order_id), UNIQUE INDEX UNIQ_A8BFE98DBA364942 (entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE purchase (id INT AUTO_INCREMENT NOT NULL, store_id INT NOT NULL, status VARCHAR(128) NOT NULL, INDEX IDX_6117D13BB092A811 (store_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE purchase_entry (id INT AUTO_INCREMENT NOT NULL, purchase_id INT NOT NULL, entry_id INT NOT NULL, INDEX IDX_FFEA1E5A558FBEB9 (purchase_id), UNIQUE INDEX UNIQ_FFEA1E5ABA364942 (entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE store (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_store (user_id INT NOT NULL, store_id INT NOT NULL, INDEX IDX_1D95A32FA76ED395 (user_id), INDEX IDX_1D95A32FB092A811 (store_id), PRIMARY KEY(user_id, store_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE warehouse (id INT AUTO_INCREMENT NOT NULL, store_id INT NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_ECB38BFCB092A811 (store_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE warehouse_product (id INT AUTO_INCREMENT NOT NULL, warehouse_id INT DEFAULT NULL, product_id INT NOT NULL, purchase_price NUMERIC(14, 4) NOT NULL, minimum_selling_price NUMERIC(14, 4) NOT NULL, selling_price NUMERIC(14, 4) NOT NULL, count INT NOT NULL, reserve_count INT NOT NULL, INDEX IDX_F4AD11D85080ECDE (warehouse_id), INDEX IDX_F4AD11D84584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE category_additional_name ADD CONSTRAINT FK_283EA49AB092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('ALTER TABLE category_additional_name ADD CONSTRAINT FK_283EA49A12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE category_additional_name ADD CONSTRAINT FK_283EA49A709D4F22 FOREIGN KEY (additional_name_id) REFERENCES additional_name (id)');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D704584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT FK_A8BFE98D8D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE order_entry ADD CONSTRAINT FK_A8BFE98DBA364942 FOREIGN KEY (entry_id) REFERENCES entry (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13BB092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('ALTER TABLE purchase_entry ADD CONSTRAINT FK_FFEA1E5A558FBEB9 FOREIGN KEY (purchase_id) REFERENCES purchase (id)');
        $this->addSql('ALTER TABLE purchase_entry ADD CONSTRAINT FK_FFEA1E5ABA364942 FOREIGN KEY (entry_id) REFERENCES entry (id)');
        $this->addSql('ALTER TABLE user_store ADD CONSTRAINT FK_1D95A32FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_store ADD CONSTRAINT FK_1D95A32FB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE warehouse ADD CONSTRAINT FK_ECB38BFCB092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('ALTER TABLE warehouse_product ADD CONSTRAINT FK_F4AD11D85080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id)');
        $this->addSql('ALTER TABLE warehouse_product ADD CONSTRAINT FK_F4AD11D84584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE user ADD store_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649B092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649B092A811 ON user (store_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649B092A811');
        $this->addSql('ALTER TABLE category_additional_name DROP FOREIGN KEY FK_283EA49AB092A811');
        $this->addSql('ALTER TABLE category_additional_name DROP FOREIGN KEY FK_283EA49A12469DE2');
        $this->addSql('ALTER TABLE category_additional_name DROP FOREIGN KEY FK_283EA49A709D4F22');
        $this->addSql('ALTER TABLE entry DROP FOREIGN KEY FK_2B219D704584665A');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B092A811');
        $this->addSql('ALTER TABLE order_entry DROP FOREIGN KEY FK_A8BFE98D8D9F6D38');
        $this->addSql('ALTER TABLE order_entry DROP FOREIGN KEY FK_A8BFE98DBA364942');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE purchase DROP FOREIGN KEY FK_6117D13BB092A811');
        $this->addSql('ALTER TABLE purchase_entry DROP FOREIGN KEY FK_FFEA1E5A558FBEB9');
        $this->addSql('ALTER TABLE purchase_entry DROP FOREIGN KEY FK_FFEA1E5ABA364942');
        $this->addSql('ALTER TABLE user_store DROP FOREIGN KEY FK_1D95A32FA76ED395');
        $this->addSql('ALTER TABLE user_store DROP FOREIGN KEY FK_1D95A32FB092A811');
        $this->addSql('ALTER TABLE warehouse DROP FOREIGN KEY FK_ECB38BFCB092A811');
        $this->addSql('ALTER TABLE warehouse_product DROP FOREIGN KEY FK_F4AD11D85080ECDE');
        $this->addSql('ALTER TABLE warehouse_product DROP FOREIGN KEY FK_F4AD11D84584665A');
        $this->addSql('DROP TABLE additional_name');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE category_additional_name');
        $this->addSql('DROP TABLE entry');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_entry');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE purchase');
        $this->addSql('DROP TABLE purchase_entry');
        $this->addSql('DROP TABLE store');
        $this->addSql('DROP TABLE user_store');
        $this->addSql('DROP TABLE warehouse');
        $this->addSql('DROP TABLE warehouse_product');
        $this->addSql('DROP INDEX IDX_8D93D649B092A811 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP store_id');
    }
}

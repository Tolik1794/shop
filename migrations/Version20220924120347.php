<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220924120347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE store_category (store_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_383B663BB092A811 (store_id), INDEX IDX_383B663B12469DE2 (category_id), PRIMARY KEY(store_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE store_category ADD CONSTRAINT FK_383B663BB092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE store_category ADD CONSTRAINT FK_383B663B12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649B092A811');
        $this->addSql('DROP INDEX IDX_8D93D649B092A811 ON user');
        $this->addSql('ALTER TABLE user DROP store_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE store_category DROP FOREIGN KEY FK_383B663BB092A811');
        $this->addSql('ALTER TABLE store_category DROP FOREIGN KEY FK_383B663B12469DE2');
        $this->addSql('DROP TABLE store_category');
        $this->addSql('ALTER TABLE `user` ADD store_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649B092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649B092A811 ON `user` (store_id)');
        $this->addSql('ALTER TABLE category DROP description');
    }
}

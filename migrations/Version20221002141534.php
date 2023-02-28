<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221002141534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD first_parent_id INT DEFAULT NULL, ADD level INT NOT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C18E775A3E FOREIGN KEY (first_parent_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_64C19C18E775A3E ON category (first_parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C18E775A3E');
        $this->addSql('DROP INDEX IDX_64C19C18E775A3E ON category');
        $this->addSql('ALTER TABLE category DROP first_parent_id, DROP level');
    }
}

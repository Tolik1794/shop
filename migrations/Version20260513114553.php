<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513114553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer last name.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD last_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP last_name');
    }
}

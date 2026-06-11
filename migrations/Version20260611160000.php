<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tax module phase 5: status_log audit column on tax_reporting_period (close/declare/reopen history).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_reporting_period ADD status_log JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_reporting_period DROP status_log');
    }
}

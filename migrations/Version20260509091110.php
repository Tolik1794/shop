<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509091110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_e9521fab736d825a RENAME TO IDX_E9521FABB4F6727B');
        $this->addSql('ALTER INDEX idx_e9521fabd4afb27a RENAME TO IDX_E9521FAB721378B0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_e9521fabb4f6727b RENAME TO idx_e9521fab736d825a');
        $this->addSql('ALTER INDEX idx_e9521fab721378b0 RENAME TO idx_e9521fabd4afb27a');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525121000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Normalize warehouse stock batch FK index names.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER INDEX idx_a8bfe98d9da9f6d1 RENAME TO IDX_A8BFE98DF6020AD4');
		$this->addSql('ALTER INDEX idx_9d06ef619da9f6d1 RENAME TO IDX_9D06EF61F6020AD4');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER INDEX idx_a8bfe98df6020ad4 RENAME TO IDX_A8BFE98D9DA9F6D1');
		$this->addSql('ALTER INDEX idx_9d06ef61f6020ad4 RENAME TO IDX_9D06EF619DA9F6D1');
	}
}

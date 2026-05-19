<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516093000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Remove legacy single order comment field after moving to order_comment.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE orders DROP comment');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE orders ADD comment TEXT DEFAULT NULL');
	}
}

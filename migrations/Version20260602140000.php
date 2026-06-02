<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace the removed refund payment type with cash in existing dev/test data.
 */
final class Version20260602140000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Replace removed refund payment type values with cash.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql("UPDATE payment SET type = 'cash' WHERE type = 'refund'");
		$this->addSql("UPDATE payment_history SET type = 'cash' WHERE type = 'refund'");
	}

	public function down(Schema $schema): void
	{
		// Irreversible: after removing the enum case, direction/reversal links carry refund semantics.
	}
}

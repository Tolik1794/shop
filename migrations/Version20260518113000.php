<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518113000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Link payment reversal rows to the original payment.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE payment ADD reverses_payment_id INT DEFAULT NULL');
		$this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D88D3F97B FOREIGN KEY (reverses_payment_id) REFERENCES payment (id) NOT DEFERRABLE');
		$this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D2108CB30 ON payment (reverses_payment_id)');
		$this->addSql('ALTER TABLE payment ADD CONSTRAINT chk_payment_not_self_reversal CHECK (reverses_payment_id IS NULL OR reverses_payment_id <> id)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D88D3F97B');
		$this->addSql('DROP INDEX UNIQ_6D28840D2108CB30');
		$this->addSql('ALTER TABLE payment DROP CONSTRAINT chk_payment_not_self_reversal');
		$this->addSql('ALTER TABLE payment DROP reverses_payment_id');
	}
}

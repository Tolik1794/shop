<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526121000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Align generated index names with current Doctrine metadata.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER INDEX idx_3a45285a76ed395 RENAME TO "IDX_28657971A76ED395"');
		$this->addSql('ALTER INDEX idx_3a45285a1ed93d47 RENAME TO "IDX_286579711ED93D47"');
		$this->addSql('ALTER INDEX idx_dace79f0a76ed395 RENAME TO "IDX_5DF8BC21A76ED395"');
		$this->addSql('ALTER INDEX idx_dace79f0fe54d947 RENAME TO "IDX_5DF8BC21FED90CCA"');
		$this->addSql('ALTER INDEX idx_f7bd1c3f1ed93d47 RENAME TO "IDX_4A91B1C51ED93D47"');
		$this->addSql('ALTER INDEX idx_f7bd1c3ffe54d947 RENAME TO "IDX_4A91B1C5FED90CCA"');
		$this->addSql('ALTER INDEX idx_4a99e7beb092a811 RENAME TO "IDX_3EF37EA1B092A811"');
		$this->addSql('ALTER INDEX idx_4a99e7be8d9f6d38 RENAME TO "IDX_3EF37EA18D9F6D38"');
		$this->addSql('ALTER INDEX idx_4a99e7be558fbeb9 RENAME TO "IDX_3EF37EA1558FBEB9"');
		$this->addSql('ALTER INDEX idx_4a99e7be4c3a3bb RENAME TO "IDX_3EF37EA14C3A3BB"');
		$this->addSql('ALTER INDEX idx_4a99e7be10daf24a RENAME TO "IDX_3EF37EA110DAF24A"');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER INDEX "IDX_28657971A76ED395" RENAME TO idx_3a45285a76ed395');
		$this->addSql('ALTER INDEX "IDX_286579711ED93D47" RENAME TO idx_3a45285a1ed93d47');
		$this->addSql('ALTER INDEX "IDX_5DF8BC21A76ED395" RENAME TO idx_dace79f0a76ed395');
		$this->addSql('ALTER INDEX "IDX_5DF8BC21FED90CCA" RENAME TO idx_dace79f0fe54d947');
		$this->addSql('ALTER INDEX "IDX_4A91B1C51ED93D47" RENAME TO idx_f7bd1c3f1ed93d47');
		$this->addSql('ALTER INDEX "IDX_4A91B1C5FED90CCA" RENAME TO idx_f7bd1c3ffe54d947');
		$this->addSql('ALTER INDEX "IDX_3EF37EA1B092A811" RENAME TO idx_4a99e7beb092a811');
		$this->addSql('ALTER INDEX "IDX_3EF37EA18D9F6D38" RENAME TO idx_4a99e7be8d9f6d38');
		$this->addSql('ALTER INDEX "IDX_3EF37EA1558FBEB9" RENAME TO idx_4a99e7be558fbeb9');
		$this->addSql('ALTER INDEX "IDX_3EF37EA14C3A3BB" RENAME TO idx_4a99e7be4c3a3bb');
		$this->addSql('ALTER INDEX "IDX_3EF37EA110DAF24A" RENAME TO idx_4a99e7be10daf24a');
	}
}

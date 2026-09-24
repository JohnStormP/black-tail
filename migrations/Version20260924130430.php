<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924130430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ledger_entries (id VARCHAR(26) NOT NULL, wallet_id VARCHAR(26) NOT NULL, type VARCHAR(16) NOT NULL, amount BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, balance_after BIGINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ledger_entries_wallet_id ON ledger_entries (wallet_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ledger_entries');
    }
}

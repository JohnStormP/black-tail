<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923132853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wallets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wallets (id VARCHAR(26) NOT NULL, player_id VARCHAR(26) NOT NULL, currency VARCHAR(3) NOT NULL, available_amount BIGINT NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_wallets_player_currency ON wallets (player_id, currency)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wallets');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bets (id VARCHAR(26) NOT NULL, player_id VARCHAR(26) NOT NULL, game_id VARCHAR(26) NOT NULL, round_id VARCHAR(26) NOT NULL, amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(32) NOT NULL, idempotency_key VARCHAR(64) NOT NULL, provider_reference VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_bets_idempotency_key ON bets (idempotency_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bets');
    }
}

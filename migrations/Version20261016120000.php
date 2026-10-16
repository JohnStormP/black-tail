<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create player_bonuses table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE player_bonuses (id VARCHAR(26) NOT NULL, player_id VARCHAR(26) NOT NULL, campaign_id VARCHAR(26) NOT NULL, tenant_id VARCHAR(26) NOT NULL, granted_amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, wagering_required_minor BIGINT NOT NULL, wagering_progress_minor BIGINT NOT NULL, status VARCHAR(32) NOT NULL, granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_player_bonuses_player_campaign ON player_bonuses (player_id, campaign_id)');
        $this->addSql('CREATE INDEX idx_player_bonuses_player_id ON player_bonuses (player_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE player_bonuses');
    }
}

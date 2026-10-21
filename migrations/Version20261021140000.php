<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create crash_rounds and crash_bets tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE crash_rounds (id VARCHAR(26) NOT NULL, status VARCHAR(32) NOT NULL, crash_point_hundredths INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, crashed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('COMMENT ON COLUMN crash_rounds.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.crashed_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE crash_bets (id VARCHAR(26) NOT NULL, round_id VARCHAR(26) NOT NULL, player_id VARCHAR(26) NOT NULL, wallet_id VARCHAR(26) NOT NULL, amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(32) NOT NULL, cash_out_hundredths INT DEFAULT NULL, payout_minor BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_crash_bets_round_status ON crash_bets (round_id, status)');
        $this->addSql('COMMENT ON COLUMN crash_bets.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crash_bets');
        $this->addSql('DROP TABLE crash_rounds');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align DB defaults and column comments with Doctrine mapping for schema:validate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ALTER bonus_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE crash_rounds ALTER started_at DROP DEFAULT');
        $this->addSql('ALTER TABLE crash_rounds ALTER crashed_at DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN crash_bets.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN crash_rounds.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN crash_rounds.started_at IS NULL');
        $this->addSql('COMMENT ON COLUMN crash_rounds.crashed_at IS NULL');
        $this->addSql('COMMENT ON COLUMN player_bonuses.credited_at IS NULL');
        $this->addSql('COMMENT ON COLUMN consumed_messages.consumed_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ALTER bonus_amount SET DEFAULT 0');
        $this->addSql('ALTER TABLE crash_rounds ALTER started_at SET DEFAULT NULL');
        $this->addSql('ALTER TABLE crash_rounds ALTER crashed_at SET DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN crash_bets.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.started_at IS \'(DC2Type:datetime_immutable_micro)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.crashed_at IS \'(DC2Type:datetime_immutable_micro)\'');
        $this->addSql('COMMENT ON COLUMN player_bonuses.credited_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consumed_messages.consumed_at IS \'(DC2Type:datetime_immutable)\'');
    }
}

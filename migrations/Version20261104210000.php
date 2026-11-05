<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add wallet bonus_amount and player_bonus credit tracking for locked bonus cash';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ADD bonus_amount BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE player_bonuses ADD wallet_id VARCHAR(26) DEFAULT NULL');
        $this->addSql('ALTER TABLE player_bonuses ADD credited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN player_bonuses.credited_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets DROP bonus_amount');
        $this->addSql('ALTER TABLE player_bonuses DROP wallet_id');
        $this->addSql('ALTER TABLE player_bonuses DROP credited_at');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indexes for risk metrics queries on bets and payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_bets_player_created_at ON bets (player_id, created_at)');
        $this->addSql('CREATE INDEX idx_payments_player_type_created_at ON payments (player_id, type, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_bets_player_created_at');
        $this->addSql('DROP INDEX idx_payments_player_type_created_at');
    }
}

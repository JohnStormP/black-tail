<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_games table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenant_games (id VARCHAR(26) NOT NULL, tenant_id VARCHAR(26) NOT NULL, game_id VARCHAR(26) NOT NULL, enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_games_tenant_game ON tenant_games (tenant_id, game_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant_games');
    }
}

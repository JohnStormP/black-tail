<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919102729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create players table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE players (id VARCHAR(26) NOT NULL, tenant_id VARCHAR(26) NOT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_players_tenant_email ON players (tenant_id, email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE players');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925141719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create games table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE games (id VARCHAR(26) NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_games_code ON games (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE games');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928125002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game_rounds table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_rounds (id VARCHAR(26) NOT NULL, game_id VARCHAR(26) NOT NULL, player_id VARCHAR(26) NOT NULL, bet_minor INT NOT NULL, stops JSON NOT NULL, total_multiplier INT NOT NULL, win_minor INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game_rounds');
    }
}

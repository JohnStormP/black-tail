<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expires_at to player_bonuses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_bonuses ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_bonuses DROP expires_at');
    }
}

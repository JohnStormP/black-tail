<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925082338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reserved_amount field in wallets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ADD reserved_amount BIGINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets DROP reserved_amount');
    }
}

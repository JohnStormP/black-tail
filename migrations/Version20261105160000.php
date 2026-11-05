<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop wallets.reserved_amount default so schema matches Doctrine mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ALTER reserved_amount DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ALTER reserved_amount SET DEFAULT 0');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919091942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenants table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenants (id VARCHAR(26) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenants');
    }
}

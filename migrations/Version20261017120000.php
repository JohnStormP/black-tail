<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index payments by provider reference for webhooks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_payments_provider_reference ON payments (provider, provider_reference) WHERE provider_reference IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_payments_provider_reference');
    }
}

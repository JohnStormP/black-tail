<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bonus_campaigns table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bonus_campaigns (id VARCHAR(26) NOT NULL, tenant_id VARCHAR(26) NOT NULL, name VARCHAR(255) NOT NULL, bonus_amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, wagering_multiplier INT NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_bonus_campaigns_tenant_id ON bonus_campaigns (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bonus_campaigns');
    }
}

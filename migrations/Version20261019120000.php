<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create risk_rules table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE risk_rules (id VARCHAR(26) NOT NULL, tenant_id VARCHAR(26) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(64) NOT NULL, threshold BIGINT NOT NULL, window_seconds INT DEFAULT NULL, action VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_risk_rules_tenant_id ON risk_rules (tenant_id)');
        $this->addSql('CREATE INDEX idx_risk_rules_tenant_status ON risk_rules (tenant_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE risk_rules');
    }
}

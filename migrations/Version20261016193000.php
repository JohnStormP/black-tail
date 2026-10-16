<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payments table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payments (id VARCHAR(26) NOT NULL, tenant_id VARCHAR(26) NOT NULL, player_id VARCHAR(26) NOT NULL, wallet_id VARCHAR(26) NOT NULL, type VARCHAR(32) NOT NULL, amount_minor BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(32) NOT NULL, idempotency_key VARCHAR(64) NOT NULL, provider VARCHAR(64) NOT NULL, provider_reference VARCHAR(128) DEFAULT NULL, failure_reason VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_payments_idempotency_key ON payments (idempotency_key)');
        $this->addSql('CREATE INDEX idx_payments_tenant_id ON payments (tenant_id)');
        $this->addSql('CREATE INDEX idx_payments_player_id ON payments (player_id)');
        $this->addSql('CREATE INDEX idx_payments_wallet_id ON payments (wallet_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payments');
    }
}

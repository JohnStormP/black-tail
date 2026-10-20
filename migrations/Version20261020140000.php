<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create consumed_messages table for Messenger consumer idempotency';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE consumed_messages (consumer VARCHAR(64) NOT NULL, message_key VARCHAR(128) NOT NULL, consumed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (consumer, message_key))');
        $this->addSql('COMMENT ON COLUMN consumed_messages.consumed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE consumed_messages');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store crash round started_at/crashed_at with microsecond precision for flight elapsed time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crash_rounds ALTER started_at TYPE TIMESTAMP(6) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE crash_rounds ALTER crashed_at TYPE TIMESTAMP(6) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN crash_rounds.started_at IS \'(DC2Type:datetime_immutable_micro)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.crashed_at IS \'(DC2Type:datetime_immutable_micro)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crash_rounds ALTER started_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE crash_rounds ALTER crashed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN crash_rounds.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN crash_rounds.crashed_at IS \'(DC2Type:datetime_immutable)\'');
    }
}

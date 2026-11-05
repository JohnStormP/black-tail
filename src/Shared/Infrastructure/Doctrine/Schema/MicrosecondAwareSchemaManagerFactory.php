<?php

namespace App\Shared\Infrastructure\Doctrine\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\SchemaManagerFactory;

final class MicrosecondAwareSchemaManagerFactory implements SchemaManagerFactory
{
    private readonly SchemaManagerFactory $inner;

    public function __construct(?SchemaManagerFactory $inner = null)
    {
        $this->inner = $inner ?? new DefaultSchemaManagerFactory();
    }

    /**
     * @return AbstractSchemaManager<PostgreSQLPlatform>
     */
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            return new MicrosecondAwarePostgreSQLSchemaManager($connection, $platform);
        }

        return $this->inner->createSchemaManager($connection);
    }
}

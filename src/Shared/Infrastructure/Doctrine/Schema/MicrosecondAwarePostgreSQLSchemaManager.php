<?php

namespace App\Shared\Infrastructure\Doctrine\Schema;

use App\Shared\Infrastructure\Doctrine\Type\DateTimeImmutableMicrosecondsType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\Types\Type;

final class MicrosecondAwarePostgreSQLSchemaManager extends PostgreSQLSchemaManager
{
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $column = parent::_getPortableTableColumnDefinition($tableColumn);
        $tableColumn = \array_change_key_case($tableColumn, \CASE_LOWER);
        $completeType = \strtolower((string) ($tableColumn['complete_type'] ?? ''));

        if (\str_contains($completeType, 'timestamp(6)')) {
            $column->setType(Type::getType(DateTimeImmutableMicrosecondsType::NAME));
        }

        return $column;
    }
}

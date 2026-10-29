<?php

namespace App\Tests\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;

final class ClickHouseSelectSpy implements ClickHouseClientInterface
{
    public ?string $lastSql = null;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private readonly array $rows,
    ) {
    }

    public function ping(): void
    {
    }

    public function execute(string $sql): void
    {
    }

    public function select(string $sql): array
    {
        $this->lastSql = $sql;

        return $this->rows;
    }
}

<?php

namespace App\Tests\Analytics\Infrastructure\Messenger;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;

final class ClickHouseExecuteSpy implements ClickHouseClientInterface
{
    /** @var list<string> */
    public array $executed = [];

    public function ping(): void
    {
    }

    public function execute(string $sql): void
    {
        $this->executed[] = $sql;
    }

    public function select(string $sql): array
    {
        return [];
    }
}

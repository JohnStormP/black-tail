<?php

namespace App\Analytics\Application\ClickHouse;

use App\Analytics\Domain\Exception\ClickHouseException;

interface ClickHouseClientInterface
{
    /**
     * @throws ClickHouseException
     */
    public function ping(): void;

    /**
     * @throws ClickHouseException
     */
    public function execute(string $sql): void;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ClickHouseException
     */
    public function select(string $sql): array;
}

<?php

namespace App\Analytics\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ClickHouseException extends DomainException
{
    public static function unreachable(string $detail): self
    {
        return new self('ClickHouse cannot be reached: '.$detail);
    }

    public static function queryFailed(string $detail): self
    {
        return new self('ClickHouse query failed: '.$detail);
    }

    public static function invalidResponse(string $detail): self
    {
        return new self('ClickHouse returned an invalid response: '.$detail);
    }
}

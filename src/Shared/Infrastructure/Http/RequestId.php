<?php

namespace App\Shared\Infrastructure\Http;

final class RequestId
{
    public const string ATTRIBUTE = 'request_id';
    public const string HEADER = 'X-Request-Id';

    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isValid(string $value): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9._-]{8,64}$/', $value);
    }
}

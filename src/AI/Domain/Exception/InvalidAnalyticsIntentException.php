<?php

namespace App\AI\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidAnalyticsIntentException extends DomainException
{
    public static function missingField(string $field): self
    {
        return new self('Analytics intent field "'.$field.'" is required');
    }

    public static function invalidMetric(string $value): self
    {
        return new self('Invalid analytics metric provided "'.$value.'"');
    }

    public static function invalidGroupBy(string $value): self
    {
        return new self('Invalid analytics group_by provided "'.$value.'"');
    }

    public static function invalidPeriod(string $value): self
    {
        return new self('Invalid analytics period provided "'.$value.'"');
    }

    public static function invalidJson(string $reason): self
    {
        return new self('Invalid analytics intent JSON: '.$reason);
    }
}

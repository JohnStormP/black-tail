<?php

namespace App\Analytics\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidAnalyticsPeriodException extends DomainException
{
    public static function invalidDate(string $value): self
    {
        return new self('Invalid analytics date format provided "'.$value.'", expected Y-m-d');
    }

    public static function emptyRange(): self
    {
        return new self('Analytics period cannot be empty');
    }

    public static function invertedRange(string $fromDate, string $toDate): self
    {
        return new self('Analytics period from "'.$fromDate.'" cannot be after to "'.$toDate.'"');
    }
}

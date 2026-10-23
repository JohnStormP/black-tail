<?php

namespace App\AI\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class LlmException extends DomainException
{
    public static function requestFailed(string $reason): self
    {
        return new self('LLM request failed: '.$reason);
    }

    public static function emptyCompletion(): self
    {
        return new self('LLM returned an empty completion');
    }

    public static function invalidResponse(string $reason): self
    {
        return new self('LLM returned an invalid response: '.$reason);
    }
}

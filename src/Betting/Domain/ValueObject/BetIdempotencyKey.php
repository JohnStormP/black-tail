<?php

namespace App\Betting\Domain\ValueObject;

use App\Betting\Domain\Exception\InvalidBetIdempotencyKeyException;

final readonly class BetIdempotencyKey
{
    private const int MAX_LENGTH = 64;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw InvalidBetIdempotencyKeyException::empty();
        }
        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidBetIdempotencyKeyException::tooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

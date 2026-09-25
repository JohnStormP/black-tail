<?php

namespace App\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidGameIdException;

final readonly class GameId
{
    private const string ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidGameIdException::empty();
        }

        if (1 !== preg_match(self::ULID_PATTERN, $value)) {
            throw InvalidGameIdException::invalidFormat($value);
        }

        return new self(strtoupper($value));
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

<?php

namespace App\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidGameCodeException;

final readonly class GameCode
{
    private const string CODE_PATTERN = '/^[a-z][a-z0-9_]{1,63}$/';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ('' === $value) {
            throw InvalidGameCodeException::empty();
        }

        $value = strtolower($value);

        if (!preg_match(self::CODE_PATTERN, $value)) {
            throw InvalidGameCodeException::invalidFormat($value);
        }

        return new self($value);
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

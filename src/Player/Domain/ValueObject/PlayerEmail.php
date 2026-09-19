<?php

namespace App\Player\Domain\ValueObject;

use App\Player\Domain\Exception\InvalidPlayerEmailException;

final readonly class PlayerEmail
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw InvalidPlayerEmailException::empty();
        }

        if (false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidPlayerEmailException::invalidFormat($value);
        }

        return new self(strtolower($value));
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

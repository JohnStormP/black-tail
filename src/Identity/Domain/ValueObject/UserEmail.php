<?php

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidUserEmailException;

final readonly class UserEmail
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw InvalidUserEmailException::empty();
        }

        if (false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidUserEmailException::invalidFormat($value);
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

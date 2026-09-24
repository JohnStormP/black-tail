<?php

namespace App\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidLedgerEntryIdException;

final readonly class LedgerEntryId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidLedgerEntryIdException::empty();
        }

        $value = strtoupper($value);

        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value)) {
            throw InvalidLedgerEntryIdException::invalidFormat($value);
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

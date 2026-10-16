<?php

namespace App\Payment\Domain\ValueObject;

use App\Payment\Domain\Exception\InvalidPaymentIdException;

final readonly class PaymentId
{
    private const string ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidPaymentIdException::empty();
        }

        if (1 !== preg_match(self::ULID_PATTERN, $value)) {
            throw InvalidPaymentIdException::invalidFormat($value);
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

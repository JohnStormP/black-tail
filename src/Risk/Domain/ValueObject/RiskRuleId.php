<?php

namespace App\Risk\Domain\ValueObject;

use App\Risk\Domain\Exception\InvalidRiskRuleIdException;

final readonly class RiskRuleId
{
    private const string ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidRiskRuleIdException::empty();
        }

        if (1 !== preg_match(self::ULID_PATTERN, $value)) {
            throw InvalidRiskRuleIdException::invalidFormat($value);
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

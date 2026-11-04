<?php

namespace App\Shared\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

final class DateTimeImmutableMicrosecondsType extends DateTimeImmutableType
{
    public const string NAME = 'datetime_immutable_micro';

    private const string FORMAT = 'Y-m-d H:i:s.u';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TIMESTAMP(6) WITHOUT TIME ZONE';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTimeImmutable) {
            throw InvalidType::new($value, self::class, ['null', \DateTimeImmutable::class]);
        }

        return $value->format(self::FORMAT);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string', \DateTimeImmutable::class]);
        }

        $parsed = \DateTimeImmutable::createFromFormat(self::FORMAT, $value);
        if (false !== $parsed) {
            return $parsed;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if (false !== $parsed) {
            return $parsed;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw InvalidFormat::new($value, self::class, self::FORMAT, $e);
        }
    }
}

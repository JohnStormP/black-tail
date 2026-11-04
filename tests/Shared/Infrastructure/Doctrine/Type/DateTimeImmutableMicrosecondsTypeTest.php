<?php

namespace App\Tests\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Infrastructure\Doctrine\Type\DateTimeImmutableMicrosecondsType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

final class DateTimeImmutableMicrosecondsTypeTest extends TestCase
{
    public function testRoundTripsMicroseconds(): void
    {
        $type = new DateTimeImmutableMicrosecondsType();
        $platform = new PostgreSQLPlatform();
        $original = new \DateTimeImmutable('2026-11-04 18:40:00.800123');

        $db = $type->convertToDatabaseValue($original, $platform);
        $this->assertSame('2026-11-04 18:40:00.800123', $db);

        $php = $type->convertToPHPValue($db, $platform);
        $this->assertInstanceOf(\DateTimeImmutable::class, $php);
        $this->assertSame('800123', $php->format('u'));
    }

    public function testParsesLegacySecondPrecisionValues(): void
    {
        $type = new DateTimeImmutableMicrosecondsType();
        $platform = new PostgreSQLPlatform();

        $php = $type->convertToPHPValue('2026-11-04 18:40:00', $platform);
        $this->assertInstanceOf(\DateTimeImmutable::class, $php);
        $this->assertSame('2026-11-04 18:40:00', $php->format('Y-m-d H:i:s'));
    }

    public function testDeclaresTimestamp6(): void
    {
        $type = new DateTimeImmutableMicrosecondsType();
        $platform = new PostgreSQLPlatform();

        $this->assertSame(
            'TIMESTAMP(6) WITHOUT TIME ZONE',
            $type->getSQLDeclaration([], $platform),
        );
    }
}

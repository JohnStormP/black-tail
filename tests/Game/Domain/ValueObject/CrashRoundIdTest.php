<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidCrashRoundIdException;
use App\Game\Domain\ValueObject\CrashRoundId;
use PHPUnit\Framework\TestCase;

final class CrashRoundIdTest extends TestCase
{
    public function testFromStringNormalizesCase(): void
    {
        $id = CrashRoundId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidCrashRoundIdException::class);

        CrashRoundId::fromString('');
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidCrashRoundIdException::class);

        CrashRoundId::fromString('not-a-ulid');
    }
}

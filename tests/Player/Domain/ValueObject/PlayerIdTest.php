<?php

namespace App\Tests\Player\Domain\ValueObject;

use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\ValueObject\PlayerId;
use PHPUnit\Framework\TestCase;

final class PlayerIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = PlayerId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidPlayerIdException::class);

        PlayerId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidPlayerIdException::class);

        PlayerId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = PlayerId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = PlayerId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

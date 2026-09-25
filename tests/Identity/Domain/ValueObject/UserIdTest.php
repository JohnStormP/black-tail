<?php

namespace App\Tests\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidUserIdException;
use App\Identity\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidUserIdException::class);

        UserId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidUserIdException::class);

        UserId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = UserId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

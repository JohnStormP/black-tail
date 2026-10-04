<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidTenantGameIdException;
use App\Game\Domain\ValueObject\TenantGameId;
use PHPUnit\Framework\TestCase;

final class TenantGameIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = TenantGameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesToUppercase(): void
    {
        $id = TenantGameId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidTenantGameIdException::class);

        TenantGameId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidTenantGameIdException::class);

        TenantGameId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = TenantGameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = TenantGameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = TenantGameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

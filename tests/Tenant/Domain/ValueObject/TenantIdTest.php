<?php

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidTenantIdException::class);

        TenantId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidTenantIdException::class);

        TenantId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

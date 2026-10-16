<?php

namespace App\Tests\Payment\Domain\ValueObject;

use App\Payment\Domain\Exception\InvalidPaymentIdException;
use App\Payment\Domain\ValueObject\PaymentId;
use PHPUnit\Framework\TestCase;

final class PaymentIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = PaymentId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesToUppercase(): void
    {
        $id = PaymentId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidPaymentIdException::class);

        PaymentId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidPaymentIdException::class);

        PaymentId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = PaymentId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = PaymentId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = PaymentId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

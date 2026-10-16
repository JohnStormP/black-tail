<?php

namespace App\Tests\Payment\Domain\ValueObject;

use App\Payment\Domain\Exception\InvalidPaymentIdempotencyKeyException;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use PHPUnit\Framework\TestCase;

final class PaymentIdempotencyKeyTest extends TestCase
{
    public function testFromStringAcceptsKey(): void
    {
        $key = PaymentIdempotencyKey::fromString('deposit-player-1');

        $this->assertSame('deposit-player-1', $key->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $key = PaymentIdempotencyKey::fromString('  deposit-player-1  ');

        $this->assertSame('deposit-player-1', $key->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidPaymentIdempotencyKeyException::class);

        PaymentIdempotencyKey::fromString('   ');
    }

    public function testFromStringRejectsTooLong(): void
    {
        $this->expectException(InvalidPaymentIdempotencyKeyException::class);

        PaymentIdempotencyKey::fromString(str_repeat('a', 65));
    }

    public function testEquals(): void
    {
        $a = PaymentIdempotencyKey::fromString('same-key');
        $b = PaymentIdempotencyKey::fromString('same-key');
        $c = PaymentIdempotencyKey::fromString('other-key');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

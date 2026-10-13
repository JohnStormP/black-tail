<?php

namespace App\Tests\Betting\Domain\ValueObject;

use App\Betting\Domain\Exception\InvalidBetIdempotencyKeyException;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use PHPUnit\Framework\TestCase;

final class BetIdempotencyKeyTest extends TestCase
{
    public function testFromStringAcceptsKey(): void
    {
        $key = BetIdempotencyKey::fromString('spin-req-001');

        $this->assertSame('spin-req-001', $key->toString());
    }

    public function testFromStringTrims(): void
    {
        $key = BetIdempotencyKey::fromString('  spin-req-001  ');

        $this->assertSame('spin-req-001', $key->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidBetIdempotencyKeyException::class);

        BetIdempotencyKey::fromString('   ');
    }

    public function testFromStringRejectsTooLong(): void
    {
        $this->expectException(InvalidBetIdempotencyKeyException::class);

        BetIdempotencyKey::fromString(str_repeat('a', 65));
    }
}

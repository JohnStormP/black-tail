<?php

namespace App\Tests\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidWalletIdException;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;

final class WalletIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = WalletId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidWalletIdException::class);

        WalletId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidWalletIdException::class);

        WalletId::fromString('not-a-ulid');
    }
}

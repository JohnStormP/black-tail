<?php

namespace App\Tests\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidLedgerEntryIdException;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use PHPUnit\Framework\TestCase;

final class LedgerEntryIdTest extends TestCase
{
    public function testFromValidUlid(): void
    {
        $id = LedgerEntryId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
        $this->assertTrue($id->equals(LedgerEntryId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV')));
    }

    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidLedgerEntryIdException::class);

        LedgerEntryId::fromString('');
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidLedgerEntryIdException::class);

        LedgerEntryId::fromString('not-a-ulid');
    }
}

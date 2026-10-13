<?php

namespace App\Tests\Betting\Domain\ValueObject;

use App\Betting\Domain\Exception\InvalidBetIdException;
use App\Betting\Domain\ValueObject\BetId;
use PHPUnit\Framework\TestCase;

final class BetIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = BetId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesToUppercase(): void
    {
        $id = BetId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidBetIdException::class);

        BetId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidBetIdException::class);

        BetId::fromString('not-a-ulid');
    }
}

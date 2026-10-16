<?php

namespace App\Tests\Bonus\Domain\ValueObject;

use App\Bonus\Domain\Exception\InvalidPlayerBonusIdException;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use PHPUnit\Framework\TestCase;

final class PlayerBonusIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = PlayerBonusId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesToUppercase(): void
    {
        $id = PlayerBonusId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidPlayerBonusIdException::class);

        PlayerBonusId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidPlayerBonusIdException::class);

        PlayerBonusId::fromString('not-a-ulid');
    }
}

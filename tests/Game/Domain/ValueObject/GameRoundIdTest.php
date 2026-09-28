<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidGameRoundIdException;
use App\Game\Domain\ValueObject\GameRoundId;
use PHPUnit\Framework\TestCase;

final class GameRoundIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = GameRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesCase(): void
    {
        $id = GameRoundId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidGameRoundIdException::class);

        GameRoundId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidGameRoundIdException::class);

        GameRoundId::fromString('not-a-ulid');
    }
}

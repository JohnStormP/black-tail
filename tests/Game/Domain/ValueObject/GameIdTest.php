<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\ValueObject\GameId;
use PHPUnit\Framework\TestCase;

final class GameIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = GameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidGameIdException::class);

        GameId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidGameIdException::class);

        GameId::fromString('not-a-ulid');
    }
}

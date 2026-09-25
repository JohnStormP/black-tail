<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidGameCodeException;
use App\Game\Domain\ValueObject\GameCode;
use PHPUnit\Framework\TestCase;

final class GameCodeTest extends TestCase
{
    public function testFromStringNormalizesCase(): void
    {
        $code = GameCode::fromString('Lucky_Reels');

        $this->assertSame('lucky_reels', $code->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidGameCodeException::class);

        GameCode::fromString('   ');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidGameCodeException::class);

        GameCode::fromString('Lucky Reels');
    }
}

<?php

namespace App\Tests\Game\Domain\Entity;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Exception\InvalidGameNameException;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameStatus;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    public function testRegisterCreatesActiveGame(): void
    {
        $id = GameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $code = GameCode::fromString('lucky_reels');
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $game = Game::register($id, $code, 'Lucky Reels', $createdAt);

        $this->assertTrue($game->id()->equals($id));
        $this->assertTrue($game->code()->equals($code));
        $this->assertSame('Lucky Reels', $game->name());
        $this->assertSame(GameStatus::Active, $game->status());
        $this->assertTrue($game->isActive());
        $this->assertEquals($createdAt, $game->createdAt());
    }

    public function testRegisterRejectsEmptyName(): void
    {
        $this->expectException(InvalidGameNameException::class);

        Game::register(
            GameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            GameCode::fromString('lucky_reels'),
            '   ',
        );
    }

    public function testDisableAndActivate(): void
    {
        $game = Game::register(
            GameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        );

        $game->disable();
        $this->assertSame(GameStatus::Disabled, $game->status());
        $this->assertFalse($game->isActive());

        $game->activate();
        $this->assertSame(GameStatus::Active, $game->status());
        $this->assertTrue($game->isActive());
    }

    public function testRename(): void
    {
        $game = Game::register(
            GameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        );

        $game->rename('Lucky Reels Deluxe');

        $this->assertSame('Lucky Reels Deluxe', $game->name());
    }
}

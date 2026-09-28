<?php

namespace App\Tests\Game\Domain\Entity;

use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Exception\InvalidGameRoundException;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Player\Domain\ValueObject\PlayerId;
use PHPUnit\Framework\TestCase;

final class GameRoundTest extends TestCase
{
    public function testRecordStoresSpinOutcome(): void
    {
        $config = LuckyReelsConfig::default();
        $result = (new LuckyReelsEngine())->evaluateStops($config, [0, 0, 0, 0, 0]);
        $createdAt = new \DateTimeImmutable('2026-03-01 12:00:00');

        $round = GameRound::record(
            GameRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            GameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            PlayerId::fromString('01CXYZ00000000000000000001'),
            100,
            $config,
            $result,
            $createdAt,
        );

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $round->id()->toString());
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $round->gameId()->toString());
        $this->assertSame('01CXYZ00000000000000000001', $round->playerId()->toString());
        $this->assertSame(100, $round->betMinor());
        $this->assertSame([0, 0, 0, 0, 0], $round->stops());
        $this->assertSame($result->totalMultiplier(), $round->totalMultiplier());
        $this->assertSame($result->winAmountMinor(100), $round->winMinor());
        $this->assertEquals($createdAt, $round->createdAt());
        $this->assertSame($round->winMinor() > 0, $round->isWin());
    }

    public function testRecordRejectsBetBelowMinimum(): void
    {
        $config = LuckyReelsConfig::default();
        $result = (new LuckyReelsEngine())->evaluateStops($config, [0, 0, 0, 0, 0]);

        $this->expectException(InvalidGameRoundException::class);

        GameRound::record(
            GameRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            GameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            PlayerId::fromString('01CXYZ00000000000000000001'),
            1,
            $config,
            $result,
        );
    }
}

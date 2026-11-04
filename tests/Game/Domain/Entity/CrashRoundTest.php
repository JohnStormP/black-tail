<?php

namespace App\Tests\Game\Domain\Entity;

use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Exception\InvalidCrashRoundTransitionException;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Domain\ValueObject\CrashRoundStatus;
use PHPUnit\Framework\TestCase;

final class CrashRoundTest extends TestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testOpenStartsWaitingWithCrashPoint(): void
    {
        $createdAt = new \DateTimeImmutable('2026-10-21 12:00:00');
        $point = CrashMultiplier::fromHundredths(250);

        $round = CrashRound::open(
            CrashRoundId::fromString(self::ROUND_ID),
            $point,
            $createdAt,
        );

        $this->assertTrue($round->id()->equals(CrashRoundId::fromString(self::ROUND_ID)));
        $this->assertSame(CrashRoundStatus::Waiting, $round->status());
        $this->assertTrue($round->crashPoint()->equals($point));
        $this->assertEquals($createdAt, $round->createdAt());
        $this->assertNull($round->startedAt());
        $this->assertNull($round->crashedAt());
    }

    public function testStartMovesWaitingToRunning(): void
    {
        $round = $this->openRound();
        $startedAt = new \DateTimeImmutable('2026-10-21 12:00:01');

        $round->start($startedAt);

        $this->assertSame(CrashRoundStatus::Running, $round->status());
        $this->assertEquals($startedAt, $round->startedAt());
    }

    public function testStartRejectsWhenNotWaiting(): void
    {
        $round = $this->openRound();
        $round->start();

        $this->expectException(InvalidCrashRoundTransitionException::class);
        $round->start();
    }

    public function testCrashMovesRunningToCrashed(): void
    {
        $round = $this->openRound();
        $round->start(new \DateTimeImmutable('2026-10-21 12:00:01'));
        $crashedAt = new \DateTimeImmutable('2026-10-21 12:00:05');

        $round->crash($crashedAt);

        $this->assertSame(CrashRoundStatus::Crashed, $round->status());
        $this->assertEquals($crashedAt, $round->crashedAt());
    }

    public function testCrashRejectsWhenNotRunning(): void
    {
        $round = $this->openRound();

        $this->expectException(InvalidCrashRoundTransitionException::class);
        $round->crash();
    }

    public function testCashOutAllowedOnlyWhileRunningBelowCrashPoint(): void
    {
        $round = $this->openRound(CrashMultiplier::fromHundredths(200));

        $this->assertFalse($round->isCashOutAllowed(CrashMultiplier::fromHundredths(150)));

        $round->start();
        $this->assertTrue($round->isCashOutAllowed(CrashMultiplier::fromHundredths(150)));
        $this->assertFalse($round->isCashOutAllowed(CrashMultiplier::fromHundredths(200)));
        $this->assertFalse($round->isCashOutAllowed(CrashMultiplier::fromHundredths(250)));

        $round->crash();
        $this->assertFalse($round->isCashOutAllowed(CrashMultiplier::fromHundredths(150)));
    }

    public function testCurrentMultiplierAtUsesElapsedTime(): void
    {
        $round = $this->openRound(CrashMultiplier::fromHundredths(200));
        $startedAt = new \DateTimeImmutable('2026-10-21 12:00:00.000');
        $round->start($startedAt);

        $atStart = $round->currentMultiplierAt($startedAt);
        $this->assertSame(100, $atStart->toHundredths());

        $laterTs = (float) $startedAt->format('U.u') + 0.5;
        $later = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $laterTs));
        self::assertInstanceOf(\DateTimeImmutable::class, $later);
        $atLater = $round->currentMultiplierAt($later);
        $this->assertSame(110, $atLater->toHundredths());
    }

    public function testCurrentMultiplierAtRejectsWhenNotRunning(): void
    {
        $round = $this->openRound();

        $this->expectException(InvalidCrashRoundTransitionException::class);
        $round->currentMultiplierAt(new \DateTimeImmutable());
    }

    private function openRound(?CrashMultiplier $point = null): CrashRound
    {
        return CrashRound::open(
            CrashRoundId::fromString(self::ROUND_ID),
            $point ?? CrashMultiplier::fromHundredths(250),
        );
    }
}

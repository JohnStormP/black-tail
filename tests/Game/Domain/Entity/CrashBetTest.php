<?php

namespace App\Tests\Game\Domain\Entity;

use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Exception\InvalidCrashBetException;
use App\Game\Domain\Exception\InvalidCrashBetTransitionException;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;

final class CrashBetTest extends TestCase
{
    private const string BET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ROUND_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string WALLET_ID = '01DXYZ00000000000000000002';

    public function testPlaceStartsOpen(): void
    {
        $bet = $this->placeBet(100);

        $this->assertSame(CrashBetStatus::Open, $bet->status());
        $this->assertSame(100, $bet->amount()->amount());
        $this->assertNull($bet->cashOutMultiplier());
        $this->assertNull($bet->payoutMinor());
    }

    public function testPlaceRejectsNonPositiveAmount(): void
    {
        $this->expectException(InvalidCrashBetException::class);

        CrashBet::place(
            CrashBetId::fromString(self::BET_ID),
            CrashRoundId::fromString(self::ROUND_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            Money::of(0, Currency::Usd),
        );
    }

    public function testCashOutWhileRunningBelowCrashPoint(): void
    {
        $round = $this->runningRound(CrashMultiplier::fromHundredths(300));
        $bet = $this->placeBet(100);
        $at = CrashMultiplier::fromHundredths(200);

        $bet->cashOut($at, $round);

        $this->assertSame(CrashBetStatus::CashedOut, $bet->status());
        $this->assertTrue($bet->cashOutMultiplier()?->equals($at));
        $this->assertSame(200, $bet->payoutMinor());
    }

    public function testCashOutRejectedWhenNotAllowed(): void
    {
        $round = $this->runningRound(CrashMultiplier::fromHundredths(150));
        $bet = $this->placeBet(100);

        $this->expectException(InvalidCrashBetException::class);
        $bet->cashOut(CrashMultiplier::fromHundredths(200), $round);
    }

    public function testCashOutRejectedWhenNotOpen(): void
    {
        $round = $this->runningRound(CrashMultiplier::fromHundredths(300));
        $bet = $this->placeBet(100);
        $bet->cashOut(CrashMultiplier::fromHundredths(200), $round);

        $this->expectException(InvalidCrashBetTransitionException::class);
        $bet->cashOut(CrashMultiplier::fromHundredths(210), $round);
    }

    public function testMarkLostFromOpen(): void
    {
        $bet = $this->placeBet(100);
        $bet->markLost();

        $this->assertSame(CrashBetStatus::Lost, $bet->status());
    }

    public function testMarkLostRejectedWhenNotOpen(): void
    {
        $bet = $this->placeBet(100);
        $bet->markLost();

        $this->expectException(InvalidCrashBetTransitionException::class);
        $bet->markLost();
    }

    private function placeBet(int $amountMinor): CrashBet
    {
        return CrashBet::place(
            CrashBetId::fromString(self::BET_ID),
            CrashRoundId::fromString(self::ROUND_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            Money::of($amountMinor, Currency::Usd),
        );
    }

    private function runningRound(CrashMultiplier $crashPoint): CrashRound
    {
        $round = CrashRound::open(CrashRoundId::fromString(self::ROUND_ID), $crashPoint);
        $round->start();

        return $round;
    }
}

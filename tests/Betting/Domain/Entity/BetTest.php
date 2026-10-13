<?php

namespace App\Tests\Betting\Domain\Entity;

use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Exception\InvalidBetTransitionException;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class BetTest extends TestCase
{
    private const string BET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';
    private const string ROUND_ID = '01DXYZ00000000000000000002';

    public function testPlaceCreatesPendingBet(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $bet = $this->placeBet(100, 'prov-1', $createdAt);

        $this->assertTrue($bet->id()->equals(BetId::fromString(self::BET_ID)));
        $this->assertTrue($bet->playerId()->equals(PlayerId::fromString(self::PLAYER_ID)));
        $this->assertTrue($bet->gameId()->equals(GameId::fromString(self::GAME_ID)));
        $this->assertTrue($bet->roundId()->equals(GameRoundId::fromString(self::ROUND_ID)));
        $this->assertSame(100, $bet->amount()->amount());
        $this->assertSame(Currency::Usd, $bet->amount()->currency());
        $this->assertSame(BetStatus::Pending, $bet->status());
        $this->assertSame('idem-1', $bet->idempotencyKey()->toString());
        $this->assertSame('prov-1', $bet->providerReference());
        $this->assertEquals($createdAt, $bet->createdAt());
    }

    public function testPlaceRejectsZeroAmount(): void
    {
        $this->expectException(InvalidBetException::class);

        $this->placeBet(0);
    }

    public function testPlaceNormalizesEmptyProviderReferenceToNull(): void
    {
        $bet = $this->placeBet(100, '   ');

        $this->assertNull($bet->providerReference());
    }

    public function testAcceptFromPending(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();

        $this->assertSame(BetStatus::Accepted, $bet->status());
    }

    public function testCancelFromPending(): void
    {
        $bet = $this->placeBet(100);
        $bet->cancel();

        $this->assertSame(BetStatus::Cancelled, $bet->status());
    }

    public function testMarkWonFromAccepted(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();
        $bet->markWon();

        $this->assertSame(BetStatus::Won, $bet->status());
    }

    public function testMarkLostFromAccepted(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();
        $bet->markLost();

        $this->assertSame(BetStatus::Lost, $bet->status());
    }

    public function testRefundFromAccepted(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();
        $bet->refund();

        $this->assertSame(BetStatus::Refunded, $bet->status());
    }

    public function testRejectsAcceptFromAccepted(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();

        $this->expectException(InvalidBetTransitionException::class);
        $bet->accept();
    }

    public function testRejectsWonFromPending(): void
    {
        $bet = $this->placeBet(100);

        $this->expectException(InvalidBetTransitionException::class);
        $bet->markWon();
    }

    public function testRejectsCancelFromAccepted(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();

        $this->expectException(InvalidBetTransitionException::class);
        $bet->cancel();
    }

    public function testRejectsTransitionFromTerminalWon(): void
    {
        $bet = $this->placeBet(100);
        $bet->accept();
        $bet->markWon();

        $this->expectException(InvalidBetTransitionException::class);
        $bet->refund();
    }

    private function placeBet(
        int $amountMinor,
        ?string $providerReference = null,
        ?\DateTimeImmutable $createdAt = null,
        string $idempotencyKey = 'idem-1',
    ): Bet {
        return Bet::place(
            BetId::fromString(self::BET_ID),
            PlayerId::fromString(self::PLAYER_ID),
            GameId::fromString(self::GAME_ID),
            GameRoundId::fromString(self::ROUND_ID),
            Money::of($amountMinor, Currency::Usd),
            BetIdempotencyKey::fromString($idempotencyKey),
            $providerReference,
            $createdAt ?? new \DateTimeImmutable('2026-01-15 12:00:00'),
        );
    }
}

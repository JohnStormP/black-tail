<?php

namespace App\Betting\Domain\Entity;

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
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bets')]
#[ORM\UniqueConstraint(name: 'uniq_bets_idempotency_key', columns: ['idempotency_key'])]
#[ORM\Index(name: 'idx_bets_player_created_at', columns: ['player_id', 'created_at'])]
class Bet
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'player_id', type: 'string', length: 26)]
    private string $playerId;

    #[ORM\Column(name: 'game_id', type: 'string', length: 26)]
    private string $gameId;

    #[ORM\Column(name: 'round_id', type: 'string', length: 26)]
    private string $roundId;

    #[ORM\Column(name: 'amount_minor', type: 'bigint')]
    private int $amountMinor;

    #[ORM\Column(name: 'currency', type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: BetStatus::class)]
    private BetStatus $status;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 64)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'provider_reference', type: 'string', length: 128, nullable: true)]
    private ?string $providerReference;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function place(
        BetId $id,
        PlayerId $playerId,
        GameId $gameId,
        GameRoundId $roundId,
        Money $amount,
        BetIdempotencyKey $idempotencyKey,
        ?string $providerReference = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        if ($amount->amount() <= 0) {
            throw InvalidBetException::nonPositiveAmount();
        }

        $bet = new self();
        $bet->id = $id->toString();
        $bet->playerId = $playerId->toString();
        $bet->gameId = $gameId->toString();
        $bet->roundId = $roundId->toString();
        $bet->amountMinor = $amount->amount();
        $bet->currency = $amount->currency()->value;
        $bet->idempotencyKey = $idempotencyKey->toString();
        $bet->providerReference = trim((string) $providerReference) ?: null;
        $bet->status = BetStatus::Pending;
        $bet->createdAt = $createdAt;

        return $bet;
    }

    public function id(): BetId
    {
        return BetId::fromString($this->id);
    }

    public function playerId(): PlayerId
    {
        return PlayerId::fromString($this->playerId);
    }

    public function gameId(): GameId
    {
        return GameId::fromString($this->gameId);
    }

    public function roundId(): GameRoundId
    {
        return GameRoundId::fromString($this->roundId);
    }

    public function amount(): Money
    {
        return Money::of($this->amountMinor, Currency::from($this->currency));
    }

    public function status(): BetStatus
    {
        return $this->status;
    }

    public function idempotencyKey(): BetIdempotencyKey
    {
        return BetIdempotencyKey::fromString($this->idempotencyKey);
    }

    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function accept(): void
    {
        if (BetStatus::Pending === $this->status()) {
            $this->status = BetStatus::Accepted;
        } else {
            throw InvalidBetTransitionException::cannotTransition($this->status(), BetStatus::Accepted);
        }
    }

    public function markWon(): void
    {
        if (BetStatus::Accepted === $this->status()) {
            $this->status = BetStatus::Won;
        } else {
            throw InvalidBetTransitionException::cannotTransition($this->status(), BetStatus::Won);
        }
    }

    public function markLost(): void
    {
        if (BetStatus::Accepted === $this->status()) {
            $this->status = BetStatus::Lost;
        } else {
            throw InvalidBetTransitionException::cannotTransition($this->status(), BetStatus::Lost);
        }
    }

    public function cancel(): void
    {
        if (BetStatus::Pending === $this->status()) {
            $this->status = BetStatus::Cancelled;
        } else {
            throw InvalidBetTransitionException::cannotTransition($this->status(), BetStatus::Cancelled);
        }
    }

    public function refund(): void
    {
        if (BetStatus::Accepted === $this->status()) {
            $this->status = BetStatus::Refunded;
        } else {
            throw InvalidBetTransitionException::cannotTransition($this->status(), BetStatus::Refunded);
        }
    }
}

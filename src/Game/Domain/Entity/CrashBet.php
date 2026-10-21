<?php

namespace App\Game\Domain\Entity;

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
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crash_bets')]
class CrashBet
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'round_id', type: 'string', length: 26)]
    private string $roundId;

    #[ORM\Column(name: 'player_id', type: 'string', length: 26)]
    private string $playerId;

    #[ORM\Column(name: 'wallet_id', type: 'string', length: 26)]
    private string $walletId;

    #[ORM\Column(name: 'amount_minor', type: 'bigint')]
    private int $amountMinor;

    #[ORM\Column(name: 'currency', type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: CrashBetStatus::class)]
    private CrashBetStatus $status;

    #[ORM\Column(name: 'cash_out_hundredths', type: 'integer', nullable: true)]
    private ?int $cashOutHundredths = null;

    #[ORM\Column(name: 'payout_minor', type: 'bigint', nullable: true)]
    private ?int $payoutMinor = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function place(
        CrashBetId $id,
        CrashRoundId $roundId,
        PlayerId $playerId,
        WalletId $walletId,
        Money $amount,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        if ($amount->amount() <= 0) {
            throw InvalidCrashBetException::nonPositiveAmount();
        }

        $bet = new self();
        $bet->id = $id->toString();
        $bet->roundId = $roundId->toString();
        $bet->playerId = $playerId->toString();
        $bet->walletId = $walletId->toString();
        $bet->amountMinor = $amount->amount();
        $bet->currency = $amount->currency()->value;
        $bet->status = CrashBetStatus::Open;
        $bet->createdAt = $createdAt;

        return $bet;
    }

    public function id(): CrashBetId
    {
        return CrashBetId::fromString($this->id);
    }

    public function roundId(): CrashRoundId
    {
        return CrashRoundId::fromString($this->roundId);
    }

    public function playerId(): PlayerId
    {
        return PlayerId::fromString($this->playerId);
    }

    public function walletId(): WalletId
    {
        return WalletId::fromString($this->walletId);
    }

    public function amount(): Money
    {
        return Money::of($this->amountMinor, Currency::from($this->currency));
    }

    public function status(): CrashBetStatus
    {
        return $this->status;
    }

    public function cashOutMultiplier(): ?CrashMultiplier
    {
        return null === $this->cashOutHundredths
            ? null
            : CrashMultiplier::fromHundredths($this->cashOutHundredths);
    }

    public function payoutMinor(): ?int
    {
        return $this->payoutMinor;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function cashOut(CrashMultiplier $at, CrashRound $round): void
    {
        if (CrashBetStatus::Open !== $this->status) {
            throw InvalidCrashBetTransitionException::cannotTransition($this->status(), CrashBetStatus::CashedOut);
        }

        if (!$round->isCashOutAllowed($at)) {
            throw InvalidCrashBetException::cashOutNotAllowed($this->id(), $at);
        }

        $this->status = CrashBetStatus::CashedOut;
        $this->cashOutHundredths = $at->toHundredths();
        $this->payoutMinor = $at->payoutMinor($this->amountMinor);
    }

    public function markLost(): void
    {
        if (CrashBetStatus::Open !== $this->status) {
            throw InvalidCrashBetTransitionException::cannotTransition($this->status(), CrashBetStatus::Lost);
        }

        $this->status = CrashBetStatus::Lost;
    }
}

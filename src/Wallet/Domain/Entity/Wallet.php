<?php

namespace App\Wallet\Domain\Entity;

use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Exception\WalletNotActiveException;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use App\Wallet\Domain\ValueObject\WalletStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'wallets')]
#[ORM\UniqueConstraint(name: 'uniq_wallets_player_currency', columns: ['player_id', 'currency'])]
class Wallet
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'player_id', type: 'string', length: 26)]
    private string $playerId;

    #[ORM\Column(name: 'currency', type: 'string', length: 3, enumType: Currency::class)]
    private Currency $currency;

    #[ORM\Column(name: 'available_amount', type: 'bigint')]
    private int $availableAmount;

    #[ORM\Column(name: 'reserved_amount', type: 'bigint')]
    private int $reservedAmount;

    #[ORM\Column(name: 'bonus_amount', type: 'bigint')]
    private int $bonusAmount;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: WalletStatus::class)]
    private WalletStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function open(
        WalletId $id,
        PlayerId $playerId,
        Currency $currency,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $wallet = new self();
        $wallet->id = $id->toString();
        $wallet->playerId = $playerId->toString();
        $wallet->currency = $currency;
        $wallet->availableAmount = 0;
        $wallet->reservedAmount = 0;
        $wallet->bonusAmount = 0;
        $wallet->status = WalletStatus::Active;
        $wallet->createdAt = $createdAt;

        return $wallet;
    }

    public function id(): WalletId
    {
        return WalletId::fromString($this->id);
    }

    public function playerId(): PlayerId
    {
        return PlayerId::fromString($this->playerId);
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function availableBalance(): Money
    {
        return Money::of($this->availableAmount, $this->currency);
    }

    public function reservedBalance(): Money
    {
        return Money::of($this->reservedAmount, $this->currency);
    }

    public function bonusBalance(): Money
    {
        return Money::of($this->bonusAmount, $this->currency);
    }

    public function status(): WalletStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return WalletStatus::Active === $this->status;
    }

    public function credit(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotCredit();
        }

        $current = Money::of(
            $this->availableAmount,
            $this->currency,
        );

        $this->availableAmount = $current
            ->add($amount)
            ->amount();
    }

    public function debit(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotDebit();
        }

        $current = Money::of(
            $this->availableAmount,
            $this->currency,
        );

        if ($current->lessThan($amount)) {
            throw InsufficientFundsException::forDebit();
        }

        $this->availableAmount = $current
            ->subtract($amount)
            ->amount();
    }

    public function reserve(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotReserve();
        }
        $current = Money::of(
            $this->availableAmount,
            $this->currency,
        );

        $reserved = Money::of(
            $this->reservedAmount,
            $this->currency,
        );

        if ($current->lessThan($amount)) {
            throw InsufficientFundsException::forReserve();
        }

        $this->availableAmount = $current
            ->subtract($amount)
            ->amount();

        $this->reservedAmount = $reserved
            ->add($amount)
            ->amount();
    }

    public function release(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotRelease();
        }
        $reserved = Money::of(
            $this->reservedAmount,
            $this->currency,
        );
        $availableAmount = Money::of(
            $this->availableAmount,
            $this->currency,
        );

        if ($reserved->lessThan($amount)) {
            throw InsufficientFundsException::forRelease();
        }
        $this->reservedAmount = $reserved
            ->subtract($amount)
            ->amount();

        $this->availableAmount = $availableAmount
            ->add($amount)
            ->amount();
    }

    public function settle(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotSettle();
        }
        $reserved = Money::of(
            $this->reservedAmount,
            $this->currency,
        );

        if ($reserved->lessThan($amount)) {
            throw InsufficientFundsException::forSettle();
        }
        $this->reservedAmount = $reserved
            ->subtract($amount)
            ->amount();
    }

    public function creditBonus(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotCreditBonus();
        }

        $current = Money::of($this->bonusAmount, $this->currency);
        $this->bonusAmount = $current->add($amount)->amount();
    }

    public function unlockBonus(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotUnlockBonus();
        }

        $bonus = Money::of($this->bonusAmount, $this->currency);
        $available = Money::of($this->availableAmount, $this->currency);

        if ($bonus->lessThan($amount)) {
            throw InsufficientFundsException::forBonusUnlock();
        }

        $this->bonusAmount = $bonus->subtract($amount)->amount();
        $this->availableAmount = $available->add($amount)->amount();
    }

    public function forfeitBonus(Money $amount): void
    {
        if (!$this->isActive()) {
            throw WalletNotActiveException::cannotForfeitBonus();
        }

        $bonus = Money::of($this->bonusAmount, $this->currency);

        if ($bonus->lessThan($amount)) {
            throw InsufficientFundsException::forBonusForfeit();
        }

        $this->bonusAmount = $bonus->subtract($amount)->amount();
    }

    public function freeze(): void
    {
        $this->status = WalletStatus::Frozen;
    }

    public function activate(): void
    {
        $this->status = WalletStatus::Active;
    }
}

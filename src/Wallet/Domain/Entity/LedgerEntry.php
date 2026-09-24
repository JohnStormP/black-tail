<?php

namespace App\Wallet\Domain\Entity;

use App\Wallet\Domain\Exception\InvalidLedgerEntryException;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ledger_entries')]
#[ORM\Index(name: 'idx_ledger_entries_wallet_id', columns: ['wallet_id'])]
class LedgerEntry
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'wallet_id', type: 'string', length: 26)]
    private string $walletId;

    #[ORM\Column(name: 'type', type: 'string', length: 16, enumType: LedgerEntryType::class)]
    private LedgerEntryType $type;

    #[ORM\Column(name: 'amount', type: 'bigint')]
    private int $amount;

    #[ORM\Column(name: 'currency', type: 'string', length: 3, enumType: Currency::class)]
    private Currency $currency;

    #[ORM\Column(name: 'balance_after', type: 'bigint')]
    private int $balanceAfter;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function record(
        LedgerEntryId $id,
        WalletId $walletId,
        LedgerEntryType $type,
        Money $amount,
        Money $balanceAfter,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        if (0 === $amount->amount()) {
            throw InvalidLedgerEntryException::zeroAmount();
        }

        if ($amount->currency() !== $balanceAfter->currency()) {
            throw InvalidLedgerEntryException::currencyMismatch();
        }

        $entry = new self();
        $entry->id = $id->toString();
        $entry->walletId = $walletId->toString();
        $entry->type = $type;
        $entry->amount = $amount->amount();
        $entry->currency = $amount->currency();
        $entry->balanceAfter = $balanceAfter->amount();
        $entry->createdAt = $createdAt;

        return $entry;
    }

    public function id(): LedgerEntryId
    {
        return LedgerEntryId::fromString($this->id);
    }

    public function walletId(): WalletId
    {
        return WalletId::fromString($this->walletId);
    }

    public function type(): LedgerEntryType
    {
        return $this->type;
    }

    public function amount(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function balanceAfter(): Money
    {
        return Money::of($this->balanceAfter, $this->currency);
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCredit(): bool
    {
        return LedgerEntryType::Credit === $this->type;
    }

    public function isDebit(): bool
    {
        return LedgerEntryType::Debit === $this->type;
    }
}

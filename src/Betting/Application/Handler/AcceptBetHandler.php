<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\AcceptBetCommand;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Exception\InvalidBetTransitionException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;

final class AcceptBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(AcceptBetCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $betId = BetId::fromString($command->betId);
            $walletId = WalletId::fromString($command->walletId);
            $entryId = LedgerEntryId::fromString($command->reserveEntryId);

            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $bet = $this->bets->getById($betId);

            if (BetStatus::Accepted === $bet->status()) {
                return;
            }

            if (BetStatus::Pending !== $bet->status()) {
                throw InvalidBetTransitionException::cannotTransition($bet->status(), BetStatus::Accepted);
            }

            if (!$wallet->playerId()->equals($bet->playerId())) {
                throw InvalidBetException::walletPlayerMismatch($betId, $walletId);
            }

            if ($wallet->currency() !== $bet->amount()->currency()) {
                throw InvalidBetException::walletCurrencyMismatch($betId, $walletId);
            }

            $money = $bet->amount();
            $existing = $this->ledger->findById($entryId);
            if (null !== $existing) {
                if (
                    !$existing->walletId()->equals($walletId)
                    || LedgerEntryType::Reserve !== $existing->type()
                    || !$existing->amount()->equals($money)
                ) {
                    throw LedgerIdempotencyConflictException::forEntry($entryId);
                }
            } else {
                $wallet->reserve($money);
                $this->wallets->save($wallet);
                $this->ledger->save(LedgerEntry::record(
                    $entryId,
                    $wallet->id(),
                    LedgerEntryType::Reserve,
                    $money,
                    $wallet->availableBalance(),
                ));
            }

            $bet->accept();
            $this->bets->save($bet);
        });
    }
}

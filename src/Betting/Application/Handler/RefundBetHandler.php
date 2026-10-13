<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\RefundBetCommand;
use App\Betting\Domain\Event\BetRefunded;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Exception\InvalidBetTransitionException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RefundBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RefundBetCommand $command): void
    {
        $event = null;

        $this->transactions->transactional(function () use ($command, &$event): void {
            $betId = BetId::fromString($command->betId);
            $walletId = WalletId::fromString($command->walletId);
            $releaseEntryId = LedgerEntryId::fromString($command->releaseEntryId);

            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $bet = $this->bets->getById($betId);

            if (BetStatus::Refunded === $bet->status()) {
                return;
            }

            if (BetStatus::Accepted !== $bet->status()) {
                throw InvalidBetTransitionException::cannotTransition($bet->status(), BetStatus::Refunded);
            }

            if (!$wallet->playerId()->equals($bet->playerId())) {
                throw InvalidBetException::walletPlayerMismatch($betId, $walletId);
            }

            if ($wallet->currency() !== $bet->amount()->currency()) {
                throw InvalidBetException::walletCurrencyMismatch($betId, $walletId);
            }

            $amount = $bet->amount();
            $this->releaseStake($wallet, $walletId, $releaseEntryId, $amount);

            $bet->refund();
            $this->bets->save($bet);

            $event = new BetRefunded($betId, $bet->playerId(), $walletId, $amount);
        });

        if (null !== $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    private function releaseStake(
        Wallet $wallet,
        WalletId $walletId,
        LedgerEntryId $entryId,
        Money $stake,
    ): void {
        $existing = $this->ledger->findById($entryId);
        if (null !== $existing) {
            if (
                !$existing->walletId()->equals($walletId)
                || LedgerEntryType::Release !== $existing->type()
                || !$existing->amount()->equals($stake)
            ) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            return;
        }

        $wallet->release($stake);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Release,
            $stake,
            $wallet->availableBalance(),
        ));
    }
}

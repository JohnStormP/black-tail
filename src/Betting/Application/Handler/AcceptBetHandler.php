<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\AcceptBetCommand;
use App\Betting\Domain\Event\BetAccepted;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AcceptBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(AcceptBetCommand $command): void
    {
        $event = null;

        $this->transactions->transactional(function () use ($command, &$event): void {
            $betId = BetId::fromString($command->betId);
            $walletId = WalletId::fromString($command->walletId);
            $entryId = LedgerEntryId::fromString($command->reserveEntryId);

            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $bet = $this->bets->getByIdForUpdate($betId);

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
            if (null !== $this->ledger->findById($entryId)) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            $wallet->reserve($money);
            $this->wallets->save($wallet);
            $this->ledger->save(LedgerEntry::record(
                $entryId,
                $wallet->id(),
                LedgerEntryType::Reserve,
                $money,
                $wallet->availableBalance(),
            ));

            $bet->accept();
            $this->bets->save($bet);

            $event = new BetAccepted($betId, $bet->playerId(), $walletId, $money);
        });

        if (null !== $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}

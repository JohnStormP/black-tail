<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\SettleBetCommand;
use App\Betting\Domain\Event\BetSettled;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Exception\InvalidBetTransitionException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetSettlementOutcome;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
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

final class SettleBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PlayerRepositoryInterface $players,
    ) {
    }

    public function __invoke(SettleBetCommand $command): void
    {
        $event = null;

        $this->transactions->transactional(function () use ($command, &$event): void {
            $betId = BetId::fromString($command->betId);
            $walletId = WalletId::fromString($command->walletId);
            $settleEntryId = LedgerEntryId::fromString($command->settleEntryId);
            $outcome = BetSettlementOutcome::from($command->outcome);
            $targetStatus = BetSettlementOutcome::Won === $outcome ? BetStatus::Won : BetStatus::Lost;
            $payoutMinor = $command->payoutMinor;

            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $bet = $this->bets->getById($betId);

            if ($targetStatus === $bet->status()) {
                return;
            }

            if (BetStatus::Accepted !== $bet->status()) {
                throw InvalidBetTransitionException::cannotTransition($bet->status(), $targetStatus);
            }

            if (!$wallet->playerId()->equals($bet->playerId())) {
                throw InvalidBetException::walletPlayerMismatch($betId, $walletId);
            }

            if ($wallet->currency() !== $bet->amount()->currency()) {
                throw InvalidBetException::walletCurrencyMismatch($betId, $walletId);
            }

            if (BetSettlementOutcome::Lost === $outcome && $payoutMinor > 0) {
                throw InvalidBetException::lostBetCannotHavePayout();
            }

            if (BetSettlementOutcome::Won === $outcome && $payoutMinor > 0 && null === $command->payoutEntryId) {
                throw InvalidBetException::payoutRequiresEntryId();
            }

            $stake = $bet->amount();
            $this->settleStake($wallet, $settleEntryId, $stake);

            if (BetSettlementOutcome::Won === $outcome && $payoutMinor > 0) {
                $payoutEntryId = LedgerEntryId::fromString((string) $command->payoutEntryId);
                $payout = Money::of($payoutMinor, $wallet->currency());
                $this->creditPayout($wallet, $payoutEntryId, $payout);
            }

            if (BetSettlementOutcome::Won === $outcome) {
                $bet->markWon();
            } else {
                $bet->markLost();
            }

            $this->bets->save($bet);

            $player = $this->players->getById($bet->playerId());
            $event = new BetSettled(
                $betId,
                $bet->playerId(),
                $walletId,
                $outcome,
                $stake,
                $payoutMinor,
                $player->tenantId(),
            );
        });

        if (null !== $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    private function settleStake(
        Wallet $wallet,
        LedgerEntryId $entryId,
        Money $stake,
    ): void {
        if (null !== $this->ledger->findById($entryId)) {
            throw LedgerIdempotencyConflictException::forEntry($entryId);
        }

        $wallet->settle($stake);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Settle,
            $stake,
            $wallet->availableBalance(),
        ));
    }

    private function creditPayout(
        Wallet $wallet,
        LedgerEntryId $entryId,
        Money $payout,
    ): void {
        if (null !== $this->ledger->findById($entryId)) {
            throw LedgerIdempotencyConflictException::forEntry($entryId);
        }

        $wallet->credit($payout);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Credit,
            $payout,
            $wallet->availableBalance(),
        ));
    }
}

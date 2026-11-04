<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\CashOutCrashBetCommand;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;

final class CashOutCrashBetHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly CrashBetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(CashOutCrashBetCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $betId = CrashBetId::fromString($command->betId);
            $at = CrashMultiplier::fromHundredths($command->cashOutHundredths);

            $hint = $this->bets->getById($betId);
            $round = $this->rounds->getByIdForUpdate($hint->roundId());
            $bet = $this->bets->getByIdForUpdate($betId);

            if (CrashBetStatus::CashedOut === $bet->status()) {
                return;
            }

            $wallet = $this->wallets->getByIdForUpdate($bet->walletId());
            $bet->cashOut($at, $round);

            $stake = $bet->amount();
            $this->settleStake(
                $wallet,
                LedgerEntryId::fromString($command->settleEntryId),
                $stake,
            );

            $payoutMinor = $bet->payoutMinor();
            if (null === $payoutMinor) {
                throw new \LogicException('Crash bet payout must be set after cash out');
            }

            $payout = Money::of($payoutMinor, $stake->currency());
            $this->creditPayout(
                $wallet,
                LedgerEntryId::fromString($command->payoutEntryId),
                $payout,
            );

            $this->bets->save($bet);
        });
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

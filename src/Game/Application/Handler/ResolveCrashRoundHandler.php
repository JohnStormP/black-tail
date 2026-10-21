<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\ResolveCrashRoundCommand;
use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Domain\ValueObject\CrashRoundStatus;
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

final class ResolveCrashRoundHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly CrashBetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly CrashRealtimePublisherInterface $realtime,
    ) {
    }

    public function __invoke(ResolveCrashRoundCommand $command): void
    {
        $justCrashedPoint = null;
        $roundId = CrashRoundId::fromString($command->roundId);

        $this->transactions->transactional(function () use ($command, $roundId, &$justCrashedPoint): void {
            $round = $this->rounds->getByIdForUpdate($roundId);
            $openBets = $this->bets->findOpenByRoundId($round->id());

            if (CrashRoundStatus::Crashed === $round->status() && [] === $openBets) {
                return;
            }

            if (CrashRoundStatus::Crashed !== $round->status()) {
                $round->crash();
                $this->rounds->save($round);
                $justCrashedPoint = $round->crashPoint()->toHundredths();
            }

            foreach ($openBets as $bet) {
                $betId = $bet->id()->toString();
                if (!\array_key_exists($betId, $command->settleEntryIdsByBetId)) {
                    throw new \InvalidArgumentException('Missing settle entry id for crash bet "'.$betId.'"');
                }

                $wallet = $this->wallets->getByIdForUpdate($bet->walletId());
                $this->settleStake(
                    $wallet,
                    $bet->walletId(),
                    LedgerEntryId::fromString($command->settleEntryIdsByBetId[$betId]),
                    $bet->amount(),
                );

                $bet->markLost();
                $this->bets->save($bet);
            }
        });

        if (null !== $justCrashedPoint) {
            $this->realtime->publishCrashed($roundId, $justCrashedPoint);
        }
    }

    private function settleStake(
        Wallet $wallet,
        WalletId $walletId,
        LedgerEntryId $entryId,
        Money $stake,
    ): void {
        $existing = $this->ledger->findById($entryId);
        if (null !== $existing) {
            if (
                !$existing->walletId()->equals($walletId)
                || LedgerEntryType::Settle !== $existing->type()
                || !$existing->amount()->equals($stake)
            ) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            return;
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
}

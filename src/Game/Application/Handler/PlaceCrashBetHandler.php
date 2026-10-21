<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\PlaceCrashBetCommand;
use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Exception\InvalidCrashBetException;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class PlaceCrashBetHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly CrashBetRepositoryInterface $bets,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(PlaceCrashBetCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $betId = CrashBetId::fromString($command->betId);
            $roundId = CrashRoundId::fromString($command->roundId);
            $playerId = PlayerId::fromString($command->playerId);
            $walletId = WalletId::fromString($command->walletId);
            $entryId = LedgerEntryId::fromString($command->reserveEntryId);
            $amount = Money::of($command->amountMinor, Currency::from($command->currency));

            $round = $this->rounds->getByIdForUpdate($roundId);
            $wallet = $this->wallets->getByIdForUpdate($walletId);

            if (!$round->acceptsBets()) {
                throw InvalidCrashBetException::roundNotAcceptingBets($roundId);
            }

            if (!$wallet->playerId()->equals($playerId)) {
                throw InvalidCrashBetException::walletPlayerMismatch($betId, $walletId);
            }

            if ($wallet->currency() !== $amount->currency()) {
                throw InvalidCrashBetException::walletCurrencyMismatch($betId, $walletId);
            }

            $existingBet = $this->bets->findById($betId);
            if (null !== $existingBet) {
                return;
            }

            $existing = $this->ledger->findById($entryId);
            if (null !== $existing) {
                if (
                    !$existing->walletId()->equals($walletId)
                    || LedgerEntryType::Reserve !== $existing->type()
                    || !$existing->amount()->equals($amount)
                ) {
                    throw LedgerIdempotencyConflictException::forEntry($entryId);
                }
            } else {
                $wallet->reserve($amount);
                $this->wallets->save($wallet);
                $this->ledger->save(LedgerEntry::record(
                    $entryId,
                    $wallet->id(),
                    LedgerEntryType::Reserve,
                    $amount,
                    $wallet->availableBalance(),
                ));
            }

            $bet = CrashBet::place($betId, $roundId, $playerId, $walletId, $amount);
            $this->bets->save($bet);
        });
    }
}

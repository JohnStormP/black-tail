<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\CreditPlayerBonusCommand;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;

final class CreditPlayerBonusHandler
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(CreditPlayerBonusCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $playerBonusId = PlayerBonusId::fromString($command->playerBonusId);
            $walletId = WalletId::fromString($command->walletId);
            $entryId = LedgerEntryId::fromString($command->creditEntryId);

            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $bonus = $this->playerBonuses->getByIdForUpdate($playerBonusId);

            if (PlayerBonusStatus::Active !== $bonus->status()) {
                throw InvalidPlayerBonusException::cannotCreditInactiveBonus($playerBonusId);
            }

            if (!$wallet->playerId()->equals($bonus->playerId())) {
                throw InvalidPlayerBonusException::walletPlayerMismatch($playerBonusId, $walletId);
            }

            if ($wallet->currency() !== $bonus->grantedAmount()->currency()) {
                throw InvalidPlayerBonusException::walletCurrencyMismatch($playerBonusId, $walletId);
            }

            $money = $bonus->grantedAmount();
            $existing = $this->ledger->findById($entryId);
            if (null !== $existing) {
                if (
                    !$existing->walletId()->equals($walletId)
                    || LedgerEntryType::BonusCredit !== $existing->type()
                    || !$existing->amount()->equals($money)
                ) {
                    throw LedgerIdempotencyConflictException::forEntry($entryId);
                }

                return;
            }

            if ($bonus->isCredited()) {
                throw InvalidPlayerBonusException::alreadyCredited($playerBonusId);
            }

            $wallet->creditBonus($money);
            $bonus->markCredited($walletId);
            $this->wallets->save($wallet);
            $this->playerBonuses->save($bonus);
            $this->ledger->save(LedgerEntry::record(
                $entryId,
                $wallet->id(),
                LedgerEntryType::BonusCredit,
                $money,
                $wallet->bonusBalance(),
            ));
        });
    }
}

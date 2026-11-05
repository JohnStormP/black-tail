<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\CancelPlayerBonusCommand;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;

final class CancelPlayerBonusHandler
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(CancelPlayerBonusCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $playerBonusId = PlayerBonusId::fromString($command->playerBonusId);
            $bonus = $this->playerBonuses->getByIdForUpdate($playerBonusId);

            if ($bonus->isCredited()) {
                if (null === $command->forfeitEntryId || '' === $command->forfeitEntryId) {
                    throw InvalidPlayerBonusException::missingForfeitEntryId($playerBonusId);
                }

                $walletId = $bonus->walletId();
                if (null === $walletId) {
                    throw InvalidPlayerBonusException::missingCreditedWallet($playerBonusId);
                }

                $wallet = $this->wallets->getByIdForUpdate($walletId);
                $this->forfeitCreditedBonus(
                    $wallet,
                    $bonus->grantedAmount(),
                    LedgerEntryId::fromString($command->forfeitEntryId),
                );
            }

            $bonus->cancel();
            $this->playerBonuses->save($bonus);
        });
    }

    private function forfeitCreditedBonus(Wallet $wallet, Money $money, LedgerEntryId $entryId): void
    {
        $existing = $this->ledger->findById($entryId);
        if (null !== $existing) {
            if (
                !$existing->walletId()->equals($wallet->id())
                || LedgerEntryType::BonusForfeit !== $existing->type()
                || !$existing->amount()->equals($money)
            ) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            return;
        }

        $wallet->forfeitBonus($money);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::BonusForfeit,
            $money,
            $wallet->bonusBalance(),
        ));
    }
}
